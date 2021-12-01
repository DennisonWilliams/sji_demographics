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
use OpenEMR\Gacl\Gacl;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;
use OpenEMR\Events\PatientDemographics\RenderEvent;
use OpenEMR\Events\PatientFinder\PatientFinderFilterEvent;
use OpenEMR\Events\AbstractBoundFilter;
use OpenEMR\Events\BoundFilter;

include_once($GLOBALS['fileroot'] .'/interface/globals.php');
include_once($GLOBALS['fileroot'] .'/library/acl.inc');
include_once($GLOBALS['fileroot'] .'/interface/forms/sji_intake/report.php');
include_once($GLOBALS['fileroot'] .'/interface/forms/sji_stride_intake/report.php');
include_once($GLOBALS['fileroot'] .'/interface/forms/sji_intake_core_variables/report.php');

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
			PatientFinderFilterEvent::EVENT_HANDLE, 
			[$this, 'addSJIPatientFinderFilter']);

		$this->eventDispatcher->addListener(
			RenderEvent::EVENT_SECTION_LIST_RENDER_BEFORE, 
			[$this, 'addSJIDemographics']);
	}

	public function addSJIPatientFinderFilter(PatientFinderFilterEvent $event) {
		// There are 4 different places the search term can come in from 
		// search_any - from the global patient search filter which does not use 
		// the dynamic ajax filter.  This is the only one we are going to support
		$search = $GLOBALS['_REQUEST']['sSearch_0'];
		if (!strlen($search)) { 
			$search = $GLOBALS['_REQUEST']['search_any'];
		}

		if (!strlen($search)) {
			return;
		}

		//error_log('search: '. print_r($search, 1));
		$sql = 'SELECT MAX(id) AS id, pid from form_sji_intake_core_variables '.
			'where aliases like ? group by pid';
		//error_log('sql: '. print_r($sql, 1));
		$res = sqlStatement($sql, array('%'.$search.'%'));
		$pids = array();
		while ($row = sqlFetchArray($res)) {
			//error_log('pid: '. print_r($row['pid'], 1));
			$pids[] = $row['pid'];
		}

		// The main search logic checks the begining of the string, we want to match anywhere
		$sql = 'SELECT pid from patient_data where '.
			'lname like ? or '.
			'fname like ? or '.
			'mname like ? or '.
			'pid like ? or '.
			'DOB like ?';
		$res = sqlStatement($sql, array(
			'%'.$search.'%',
			'%'.$search.'%',
			'%'.$search.'%',
			'%'.$search.'%',
			'%'.$search.'%'
		));
		while ($row = sqlFetchArray($res)) {
			//error_log('pid: '. print_r($row['pid'], 1));
			$pids[] = $row['pid'];
		}

		if (count($pids)) {
			$where_clause = 'patient_data.pid in ('. implode(",", $pids) .') or 1 ';
			$event->getBoundFilter()->setFilterClause($where_clause);
		}
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
		$pid = $event->getPid();
		if (acl_check('patients', 'demo')) { 
			echo "<section>\n";

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

			echo "<div id=\"SJI\" >\n".
				"<ul class=\"tabNav\">\n";

			// display tabs for: basic participant info,
			// and all intakes: main, CV, STRIDE

			echo '<li class="current"> <a href="#" id="header_tab_Who"> Who</a></li>'."\n";

			// TODO: data show when it was last updated?

			$query = "select count(*) as ct from form_sji_intake_core_variables where pid=?";
			$res = sqlStatement($query, array($pid));
			$cv_rows = sqlFetchArray($res);
			if (
				isset($cv_rows['ct']) && 
				$cv_rows['ct'] > 0 && 
				acl_check('forms', 'intake')
			) {
				echo '<li class="">'.
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

				echo '<li class="">'.
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
				echo '<li class="">'.
					'<a href="#" id="header_tab_Stride">x STRIDE</a>'.
					'</li>'."\n";
			}

			echo '</ul>'. "\n".
			  '<div class="tabContainer">'. "\n".
			  '<div class="tab current">'. "\n".
			  '<table class="table table-borderless">'. "\n".
			  '<tbody>'. "\n";

			// Get name, gender
			$patient_data = $this->getPatientData($pid);
			$gender = $this->getGender($patient_data['sex']);
			$patient_cv = $this->getCoreVariables($pid);

			echo '<tr><td class="label_custom" colspan=1 id="label_title">'.
				'<span id="label_title" class="bold">Name:</span></td>'.
				"\n<td class='text data' colspan=1 id=text_title ";

			if (isset($patient_data['title'])) {
				echo '>'. $patient_data['title'] .' ';

			}else {
				echo '>';
			}

			if (isset($patient_data['fname'])) {
				echo $patient_data['fname'] .' ';
			}

			if (isset($patient_data['mname'])) {
				echo $patient_data['mname'] .' ';
			}

			if (isset($patient_data['lname'])) {
				echo $patient_data['lname'];
			}
			echo "</td>\n</tr>\n";

			if (isset($patient_cv['aliases'])) {
				echo "<tr>\n<td class='label_custom' colspan=1 id='label_aliases'>\n";
				echo "<span id='label_aliases' class='bold'>". xl('Aliases') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_aliases">';
				echo $patient_cv['aliases'] ."\n</td>\n</tr>\n";
			}

			if (isset($patient_cv['pronouns'])) {
				echo "<tr>\n<td class='label_custom' colspan=1 id='label_pronouns'>\n";
				echo "<span id='label_pronouns' class='bold'>". xl('Pronouns') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_pronouns">';
				echo $patient_cv['pronouns'] ."\n</td>\n</tr>\n";
			}

			if (isset($gender['title'])) {
				echo "<tr>\n<td class='label_custom' colspan=1 id='label_sex'>\n";
				echo "<span id='label_sex' class='bold'>". xl('Gender') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_gender">';
				echo $gender['title'] ."\n</td>\n</tr>\n";

			} else if (isset($patient_data['sex'])) {
				echo "<tr>\n<td class='label_custom' colspan=1 id='label_sex'>\n";
				echo "<span id='label_sex' class='bold'>". xl('Gender') .":</span></td>\n".
				  '<td class="text data" colspan=1 id="text_gender">';
				echo $patient_data['sex'] ."\n</td>\n</tr>\n";
			}

			// close of the parent table and div."tab current"
			echo "</tbody></table></div>\n";

			// create tab for CV
			if ( isset($cv_rows['ct']) && $cv_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
				echo "<div class='tab'>\n";
				echo sji_intake_core_variables_report_string($pid);
				echo "</div>\n";
			}

			// create tab for Intake
			if (isset($intake_rows['ct']) && $intake_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
				echo "<div class='tab'>\n";
				echo sji_intake_report_string($pid);
				echo "</div>\n";
			}

			// create tab for STRIDE
			if (isset($stride_rows['ct']) && $stride_rows['ct'] > 0 && acl_check('forms', 'intake')
			) {
			   echo "<div class='tab'>\n";
			   echo sji_stride_intake_report_string($pid);
			   echo "</div>\n";
			}

			//close off div.tabContainer and div#SJI
			echo "</div></div>";

			echo '</section>'."\n";
		} // if the user has patients demo permission
	} // function

} // class
