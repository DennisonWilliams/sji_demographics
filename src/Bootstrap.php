<?php
/**
 * This module will display a demographics panel in the participant chart
 * that reflects the demograpics 
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2021 Stephen Nielson <stephen@nielson.org>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\SJIDemographics;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Note the below use statements are importing classes from the OpenEMR core codebase
 */
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;
use OpenEMR\Events\PatientDemographics\RenderEvent;

// TODO: we should bail if the sji_intake forms do not exist
// idealy we should install those forms with this module
// we might be able to do this in the code below
require_once('/interface/forms/sji_intake_core_variables/report.php');
require_once('/interface/forms/sji_intake/report.php');
require_once('/interface/forms/sji_stride_intake/report.php');

class Bootstrap
{
    const MODULE_INSTALLATION_PATH = "";
    const MODULE_NAME = "sji-demographics";
	/**
	 * @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system
	 */
	private $eventDispatcher;

	public function __construct(EventDispatcherInterface $eventDispatcher)
	{
	    $this->eventDispatcher = $eventDispatcher;
	}

	public function subscribeToEvents()
	{
		$this->eventDispatcher->addListener(
			RenderEvent::EVENT_SECTION_LIST_RENDER_BEFORE, 
			[$this, 'addSJIDemographics']);
	}

	private function getPatientData($pid) {
		$sql = 'SELECT title,fname,mname,lname,sex from patient_data'.
		       ' where pid=? ORDER BY id DESC limit 1';
		$res = sqlStatement($sql, array($pid));
		return sqlFetchArray($res);
	}

	private function getGender($sex) {
		$sql = 'SELECT title from list_options where list_id="sex" and option_id=?';
		$res = sqlStatement($sql, array($sex));
		$gender = sqlFetchArray($res);
	}

	private function getCoreVariables($pid) {
		// Get aliases and pronouns
		$sql = 'SELECT aliases,pronouns from form_sji_intake_core_variables '.
		'WHERE pid=? ORDER BY date DESC LIMIT 1';
		$res = sqlStatement($sql, array($pid));
		return sqlFetchArray($res);
	}

	// TODO: add js to hide the default demographics "card"
	public function addSJIDemographics(RenderEvent $event) {
		$widget = '';
		if (acl_check('patients', 'demo')) { 
			$widget .= "<tr>\n<td>\n";

			// SJI Demographics expand collapse widget
			$widgetTitle = xl("SJI Participant");
			$widgetLabel = "intakes";
			$widgetButtonLabel = '';
			$widgetButtonLink = "";
			$widgetButtonClass = "";
			$linkMethod = "html";
			$bodyClass = "";
			$widgetAuth = 0;
			$fixedWidth = true;
			expand_collapse_widget(
			    $widgetTitle,
			    $widgetLabel,
			    $widgetButtonLabel,
			    $widgetButtonLink,
			    $widgetButtonClass,
			    $linkMethod,
			    $bodyClass,
			    $widgetAuth,
			    $fixedWidth
			);

			$widget .= "<div id=\"SJI\" >\n<ul class=\"tabNav\">\n";

			// display tabs for: basic participant info,
			// and all intakes: main, CV, STRIDE

			$widget .= '<li class="current"> <a href="#" id="header_tab_Who"> Who</a></li>'."\n";

			// TODO: data show when it was last updated?

			$query = "select count(*) as ct from form_sji_intake_core_variables where pid=?";
			$res = sqlStatement($query, array($pid));
			$cv_rows = sqlFetchArray($res);
			if (
				isset($cv_rows['ct']) && 
				$cv_rows['ct'] > 0 && 
				acl_check('forms', 'intake')
			) {
				$widget .= '<li class="">'.
					'<a href="#" id="header_tab_CV">Core Variables</a>'.
					'</li>'."\n";
			}

			$query = "select count(*) as ct from form_sji_intake where pid=?";
			$res = sqlStatement($query, array($pid));
			$intake_rows = sqlFetchArray($res);

			if (
				isset($intake_rows['ct']) && 
				$intake_rows['ct'] > 0 && 
				acl_check('forms', 'intake')
			) {

				$widget .= '<li class="">'.
					'<a href="#" id="header_tab_Intake">'.
					'Intake</a> </li>'."\n";
			}

			$query = "select count(*) as ct from form_sji_stride_intake where pid=?";
			$res = sqlStatement($query, array($pid));
			$stride_rows = sqlFetchArray($res);
			if (
				isset($stride_rows['ct']) && 
				$stride_rows['ct'] > 0 && 
				acl_check('forms', 'intake')) {
				$widget .= '<li class="">'.
					'<a href="#" id="header_tab_Stride">x STRIDE</a>'.
					'</li>'."\n";
			}

			$widget .= '</ul>'.
			  '<div class="tabContainer">'.
			  '<div class="tab current">'.
			  '<table border=0 cellpadding=0>'.
			  '<tbody>';

			// Get name, gender
			$patient_data = getPatientData($pid);
			$gender = getGender($patient_data['sex']);
			$patient_cv = getCoreVariables($pid);

			$widget .= '<tr><td class="label_custom" colspan=1 id="label_title">'.
				'<span id="label_title">Name:</span></td>'.
				"\n<td class='text data' colspan=1 id=text_title ";

			if (isset($patient_data['title'])) {
				$widget .= '>'. $patient_data['title'] .' ';

			}else {
				$widget .= '>';
			}

			if (isset($patient_data['fname'])) {
				$widget .= $patient_data['fname'] .' ';
			}

			if (isset($patient_data['mname'])) {
				$widget .= $patient_data['mname'] .' ';
			}

			if (isset($patient_data['lname'])) {
				$widget .= $patient_data['lname'];
			}
			$widget .= "</td>\n</tr>\n";

			if (isset($patient_cv['aliases'])) {
				$widget .= "<tr>\n<td class='label_custom' colspan=1 id='label_aliases'>\n";
				$widget .= "<span id='label_aliases'>". xl('Aliases') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_aliases">';
				$widget .= $patient_cv['aliases'] ."\n</td>\n</tr>\n";
			}

			if (isset($patient_cv['pronouns'])) {
				$widget .= "<tr>\n<td class='label_custom' colspan=1 id='label_pronouns'>\n";
				$widget .= "<span id='label_pronouns'>". xl('Pronouns') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_pronouns">';
				$widget .= $patient_cv['pronouns'] ."\n</td>\n</tr>\n";
			}

			if (isset($gender['title'])) {
				$widget .= "<tr>\n<td class='label_custom' colspan=1 id='label_sex'>\n";
				$widget .= "<span id='label_sex'>". xl('Gender') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_gender">';
				$widget .= $gender['title'] ."\n</td>\n</tr>\n";

			} else if (isset($patient_data['sex'])) {
				$widget .= "<tr>\n<td class='label_custom' colspan=1 id='label_sex'>\n";
				$widget .= "<span id='label_sex'>". xl('Gender') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_gender">';
				$widget .= $patient_data['sex'] ."\n</td>\n</tr>\n";
			}

			// close of the parent table and div."tab current"
			$widget .= "</tbody></table></div>\n";

			// create tab for CV
			if ( isset($cv_rows['ct']) && $cv_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
				$widget .= "<div class='tab'>\n";
				$widget .= sji_intake_core_variables_reporti_string($pid);
				$widget .= "</div>\n";
			}

			// create tab for Intake
			if (isset($intake_rows['ct']) && $intake_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
				$widget .= "<div class='tab'>\n";
				$widget .= sji_intake_report_string($pid);
				$widget .= "</div>\n";
			}

			// create tab for STRIDE
			if (isset($stride_rows['ct']) && $stride_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
			   $widget .= "<div class='tab'>\n";
			   $widget .= sji_stride_intake_report_string($pid);
			   $widget .= "</div>\n";
			}

			//close off div.tabContainer and div#SJI
			$widget .= "</div></div>";

			$widget .= '</div>'.
				'</div>'.
				'</div>'.
				'</td>'.
				'</tr>';
			print $widget;
		} // if the user has patients demo permission
	} // function

} // class
