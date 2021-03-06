<?php
require_once('Classes/class.tx_odshtml2pdf.php');

// generate original content
require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('frontend') . 'Classes/Page/PageGenerator.php';
$GLOBALS['TT']->push('pagegen.php, initialize');
// Initialization of some variables
\TYPO3\CMS\Frontend\Page\PageGenerator::pagegenInit();
// Global content object...
$GLOBALS['TSFE']->newCObj();
$GLOBALS['TT']->pull();
// Content generation
// If this is an array, it's a sign that this script is included in order to include certain INT-scripts
if (!$GLOBALS['TSFE']->isINTincScript()) {
	$GLOBALS['TT']->push('pagegen.php, render');
	\TYPO3\CMS\Frontend\Page\PageGenerator::renderContent();
	$GLOBALS['TSFE']->setAbsRefPrefix();
	$GLOBALS['TT']->pull();
}

// instead of calling processOutput...
//---------------------------- begin ProcessOutput --------------
// substitute fe user
$token = trim($GLOBALS['TSFE']->config['config']['USERNAME_substToken']);
$token = $token ? $token : '<!--###USERNAME###-->';
if (strpos($GLOBALS['TSFE']->content, $token)) {
	$GLOBALS['TSFE']->set_no_cache();
	if ($GLOBALS['TSFE']->fe_user->user['uid']) {
		$GLOBALS['TSFE']->content = str_replace($token,$GLOBALS['TSFE']->fe_user->user['uid'],$GLOBALS['TSFE']->content);
	}
}
// Substitutes get_URL_ID in case of GET-fallback
if ($GLOBALS['TSFE']->getMethodUrlIdToken) {
	$GLOBALS['TSFE']->content = str_replace($GLOBALS['TSFE']->getMethodUrlIdToken, $GLOBALS['TSFE']->fe_user->get_URL_ID, $GLOBALS['TSFE']->content);
}

// Tidy up the code, if flag...
if ($GLOBALS['TSFE']->TYPO3_CONF_VARS['FE']['tidy_option'] == 'output') {
	$GLOBALS['TT']->push('Tidy, output','');
	$GLOBALS['TSFE']->content = $GLOBALS['TSFE']->tidyHTML($GLOBALS['TSFE']->content);
	$GLOBALS['TT']->pull();
}
// XHTML-clean the code, if flag set
if ($GLOBALS['TSFE']->doXHTML_cleaning() == 'output') {
	$GLOBALS['TT']->push('XHTML clean, output','');
	$XHTML_clean = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_parsehtml');
	$GLOBALS['TSFE']->content = $XHTML_clean->XHTML_clean($GLOBALS['TSFE']->content);
	$GLOBALS['TT']->pull();
}
//---------------------------- end ProcessOutput --------------

// ------------------------ Handle UserInt Objects --------------------------
// ********************************
// $GLOBALS['TSFE']->config['INTincScript']
// *******************************
if ($GLOBALS['TSFE']->isINTincScript()) {
	$GLOBALS['TT']->push('Non-cached objects','');
	$INTiS_config = $GLOBALS['TSFE']->config['INTincScript'];
	$GLOBALS['TSFE']->set_no_cache();
		// Special feature: Include libraries
		$GLOBALS['TT']->push('Include libraries');
		reset($INTiS_config);
		while(list(,$INTiS_cPart)=each($INTiS_config)) {
			if ($INTiS_cPart['conf']['includeLibs']) {
				$INTiS_resourceList = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',',$INTiS_cPart['conf']['includeLibs'],1);
				$GLOBALS['TT']->setTSlogMessage('Files for inclusion: "'.implode(', ',$INTiS_resourceList).'"');
				reset($INTiS_resourceList);
				while(list(,$INTiS_theLib)=each($INTiS_resourceList)) {
					$INTiS_incFile=$GLOBALS['TSFE']->tmpl->getFileName($INTiS_theLib);
					if ($INTiS_incFile) {
						require_once('./'.$INTiS_incFile);
					} else {
						$GLOBALS['TT']->setTSlogMessage('Include file "'.$INTiS_theLib.'" did not exist!',2);
					}
				}
			}
		}
		$GLOBALS['TT']->pull();
		$GLOBALS['TSFE']->INTincScript();
	$GLOBALS['TT']->pull();
}
//---------------------------- end Handle UserInt Objects --------------

//---------------------------- make links absolute --------------

function fix_links_callback($matches){
	return $matches[1].\TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($matches[2]).$matches[3];
}

$GLOBALS['TSFE']->content = preg_replace_callback('/(<a [^>]*href=\")(?!#)(.*?)(\")/',     'fix_links_callback', $GLOBALS['TSFE']->content );
$GLOBALS['TSFE']->content = preg_replace_callback('/(<form [^>]*action=\")(?!#)(.*?)(\")/','fix_links_callback', $GLOBALS['TSFE']->content );
$GLOBALS['TSFE']->content = preg_replace_callback('/(<img [^>]*src=\")(?!#)(.*?)(\")/',    'fix_links_callback', $GLOBALS['TSFE']->content );
$GLOBALS['TSFE']->content = preg_replace_callback('/(<link [^>]*href=\")(?!#)(.*?)(\")/',  'fix_links_callback', $GLOBALS['TSFE']->content );

//---------------------------- end make links absolute --------------

$output = tx_odshtml2pdf::convert($GLOBALS['TSFE']->content);

if($output[1]) {
	header('Content-type: application/pdf');
	$GLOBALS['TSFE']->content = $output[1];
} else {
	// don't cache errors
	$GLOBALS['TSFE']->set_no_cache();
	$GLOBALS['TSFE']->content = '<html><head><title>wkhtmltopdf problem</title></head><body><h1>wkhtmltopdf problem:</h1>';
	if($output[0]) {
		$GLOBALS['TSFE']->content.='<p>wkhtmltopdf produced the following errors:</p>';
		$GLOBALS['TSFE']->content.='<pre>' . htmlentities($output[0]) . '</pre>';
	} else {
		$GLOBALS['TSFE']->content.= '<p>wkhtmltopdf produced no pdf-output.</p>';
	}
	$GLOBALS['TSFE']->content.='</body></html>';
}
?>