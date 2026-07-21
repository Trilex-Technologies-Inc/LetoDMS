<?php
/**
 * Implementation of Calendar view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for Calendar view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Calendar extends LetoDMS_Bootstrap_Style {

	function generateCalendarArrays() { /* {{{ */
		$this->monthNames = array( getMLText("january"),
												 getMLText("february"),
												 getMLText("march"),
												 getMLText("april"),
												 getMLText("may"),
												 getMLText("june"),
												 getMLText("july"),
												 getMLText("august"),
												 getMLText("september"),
												 getMLText("october"),
												 getMLText("november"),
												 getMLText("december") );

		$this->dayNamesLong = array( getMLText("sunday"),
													 getMLText("monday"),
													 getMLText("tuesday"),
													 getMLText("wednesday"),
													 getMLText("thursday"),
													 getMLText("friday"),
													 getMLText("saturday") );

		$this->dayNames = array();
		foreach ( $this->dayNamesLong as $dn ){
			 $this->dayNames[] = substr($dn,0,3);
		}
	} /* }}} */

	// Calculate the number of days in a month, taking into account leap years.
	function getDaysInMonth($month, $year) { /* {{{ */
		if ($month < 1 || $month > 12) return 0;

		$daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
		$d = $daysInMonth[$month - 1];

		if ($month == 2){

			if ($year%4 == 0){

				if ($year%100 == 0){

					if ($year%400 == 0) $d = 29;
				}
				else $d = 29;
			}
		}
		return $d;
	} /* }}} */

	// Adjust dates to allow months > 12 and < 0 and day<0 or day>days of the month
	function adjustDate(&$day,&$month,&$year) { /* {{{ */
		$d=getDate(mktime(12,0,0, $month, $day, $year));
		$month=$d["mon"];
		$day=$d["mday"];
		$year=$d["year"];
	} /* }}} */

	// Generate the HTML for a given month
	function getMonthHTML($month, $year) { /* {{{ */
		if (!isset($this->monthNames)) $this->generateCalendarArrays();
		if (!isset($this->dayNames)) $this->generateCalendarArrays();

		$startDay = $this->firstdayofweek;

		$day=1;
		$this->adjustDate($day,$month,$year);

		$daysInMonth = $this->getDaysInMonth($month, $year);
		$date = getdate(mktime(12, 0, 0, $month, 1, $year));

		$first = $date["wday"];
		$monthName = $this->monthNames[$month - 1];

		$s  = "<table class=\"table table-condensed calendar-month\">\n";

		$s .= "<thead><tr class=\"calendar-month-title\">\n";
		$s .= "<th colspan=\"7\"><a href=\"../out/out.Calendar.php?mode=m&year=".$year."&month=".$month."\">".$monthName." <i class=\"icon-chevron-right\"></i></a></th>\n";
		$s .= "</tr>\n";

		$s .= "<tr class=\"calendar-weekdays\">\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+1)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+2)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+3)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+4)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+5)%7] . "</th>\n";
		$s .= "<th class=\"header\">" . $this->dayNames[($startDay+6)%7] . "</th>\n";
		$s .= "</tr></thead>\n<tbody>\n";

		// We need to work out what date to start at so that the first appears in the correct column
		$d = $startDay + 1 - $first;
		while ($d > 1) $d -= 7;

		// Make sure we know when today is, so that we can use a different CSS style
		$today = getdate(time());

		while ($d <= $daysInMonth)
		{
			$s .= "<tr>\n";

			for ($i = 0; $i < 7; $i++){

				$class = ($year == $today["year"] && $month == $today["mon"] && $d == $today["mday"]) ? "today" : "";
				if ($d < 1 || $d > $daysInMonth) $class .= " calendar-empty-day";
				$s .= "<td class=\"$class\">";

				if ($d > 0 && $d <= $daysInMonth){

					$s .= "<a href=\"../out/out.Calendar.php?mode=w&year=".$year."&month=".$month."&day=".$d."\">".$d."</a>";
							}
				else $s .= "&nbsp;";

				$s .= "</td>\n";
				$d++;
			}
			$s .= "</tr>\n";
		}

		$s .= "</tbody></table>\n";

		return $s;
	} /* }}} */

	function printYearTable($year) { /* {{{ */
		print "<div class=\"calendar-year-grid\">\n";
		for ($month = 1; $month <= 12; $month++)
			print "<section class=\"calendar-month-card\">" . $this->getMonthHTML($month, $year) . "</section>\n";
		print "</div>\n";
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$mode = $this->params['mode'];
		$year = $this->params['year'];
		$month = $this->params['month'];
		$day = $this->params['day'];
		$this->firstdayofweek = $this->params['firstdayofweek'];

		$this->adjustDate($day,$month,$year);

		$this->htmlStartPage(getMLText("calendar"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation("", "calendar",array($day,$month,$year));

		if ($mode=="y"){

			echo "<div class=\"calendar-page-header\">";
			echo "<div><span class=\"calendar-eyebrow\">" . getMLText("calendar") . "</span><h1>" . getMLText("year_view") . ": " . $year . "</h1></div>";
			echo "<div class=\"btn-group calendar-period-nav\">";
			print "<a class=\"btn\" href=\"../out/out.Calendar.php?mode=y&year=".($year-1)."\" title=\"Previous year\"><i class=\"icon-chevron-left\"></i></a>";
			print "<a class=\"btn\" href=\"../out/out.Calendar.php?mode=y\">" . date('Y') . "</a>";
			print "<a class=\"btn\" href=\"../out/out.Calendar.php?mode=y&year=".($year+1)."\" title=\"Next year\"><i class=\"icon-chevron-right\"></i></a>";
			echo "</div></div>";

			echo "<div class=\"calendar-year-shell\">";
			$this->printYearTable($year);
			echo "</div>";

		}else if ($mode=="m"){

			if (!isset($this->dayNamesLong)) $this->generateCalendarArrays();
			if (!isset($this->monthNames)) $this->generateCalendarArrays();

			$days=$this->getDaysInMonth($month, $year);
			$today = getdate(time());
			$events = getEventsInInterval(mktime(0,0,0, $month, 1, $year), mktime(23,59,59, $month, $days, $year));

			echo "<div class=\"calendar-page-header calendar-month-header\">";
			echo "<div><span class=\"calendar-eyebrow\">" . getMLText("month_view") . "</span><h1>" . htmlspecialchars($this->monthNames[$month-1]." ".$year) . "</h1><p>" . count($events) . " " . (count($events) == 1 ? "event" : "events") . "</p></div>";
			echo "<div class=\"calendar-header-actions\"><div class=\"btn-group calendar-period-nav\">";
			print "<a class=\"btn\" href=\"../out/out.Calendar.php?mode=m&year=".$year."&month=".($month-1)."\" title=\"Previous month\" aria-label=\"Previous month\"><i class=\"icon-chevron-left\"></i></a>";
			print "<a class=\"btn calendar-today-button\" href=\"../out/out.Calendar.php?mode=m\">Today</a>";
			print "<a class=\"btn\" href=\"../out/out.Calendar.php?mode=m&year=".$year."&month=".($month+1)."\" title=\"Next month\" aria-label=\"Next month\"><i class=\"icon-chevron-right\"></i></a>";
			echo "</div><a class=\"btn btn-primary calendar-add-event\" href=\"../out/out.AddEvent.php\"><span aria-hidden=\"true\">+</span> " . getMLText("add_event") . "</a></div></div>";

			$firstWeekday = (int) date('w', mktime(12, 0, 0, $month, 1, $year));
			$leadingDays = ($firstWeekday - $this->firstdayofweek + 7) % 7;
			$totalCells = (int) ceil(($leadingDays + $days) / 7) * 7;

			echo "<section class=\"calendar-month-board\" aria-label=\"" . htmlspecialchars($this->monthNames[$month-1]." ".$year) . "\">";
			echo "<div class=\"calendar-month-weekdays\">";
			for ($i=0; $i<7; $i++) echo "<div>" . htmlspecialchars($this->dayNames[($this->firstdayofweek+$i)%7]) . "</div>";
			echo "</div><div class=\"calendar-month-grid\">";

			for ($cell=0; $cell<$totalCells; $cell++) {
				$dayNumber = $cell - $leadingDays + 1;
				$cellTime = mktime(12, 0, 0, $month, $dayNumber, $year);
				$cellDate = getdate($cellTime);
				$isCurrentMonth = ($cellDate['mon'] == $month && $cellDate['year'] == $year);
				$isToday = ($cellDate['year'] == $today['year'] && $cellDate['mon'] == $today['mon'] && $cellDate['mday'] == $today['mday']);
				$isSelected = ($isCurrentMonth && $cellDate['mday'] == $day);
				$classes = 'calendar-day';
				if (!$isCurrentMonth) $classes .= ' calendar-day-muted';
				if ($isToday) $classes .= ' calendar-day-today';
				if ($isSelected) $classes .= ' calendar-day-selected';
				echo "<article class=\"".$classes."\">";
				echo "<a class=\"calendar-day-number\" href=\"../out/out.Calendar.php?mode=w&year=".$cellDate['year']."&month=".$cellDate['mon']."&day=".$cellDate['mday']."\" aria-label=\"".htmlspecialchars($this->dayNamesLong[$cellDate['wday']]." ".$cellDate['mday'])."\">".$cellDate['mday']."</a>";
				if ($isCurrentMonth) {
					$dayStart = mktime(0, 0, 0, $month, $cellDate['mday'], $year);
					$dayEnd = mktime(23, 59, 59, $month, $cellDate['mday'], $year);
					$dayEvents = array();
					foreach ($events as $event) if ($event['start'] <= $dayEnd && $event['stop'] >= $dayStart) $dayEvents[] = $event;
					if (count($dayEvents)) echo "<div class=\"calendar-day-events\">";
					foreach (array_slice($dayEvents, 0, 3) as $event) {
						$name = htmlspecialchars($event['name']);
						echo "<a class=\"calendar-event-chip\" href=\"../out/out.ViewEvent.php?id=".(int)$event['id']."\" title=\"".$name."\"><i></i><span>".$name."</span></a>";
					}
					if (count($dayEvents) > 3) echo "<a class=\"calendar-event-more\" href=\"../out/out.Calendar.php?mode=w&year=".$year."&month=".$month."&day=".$cellDate['mday']."\">+".(count($dayEvents)-3)." more</a>";
					if (count($dayEvents)) echo "</div>";
				}
				echo "</article>";
			}
			echo "</div></section>";

		}else{

			if (!isset($this->dayNamesLong)) $this->generateCalendarArrays();
			if (!isset($this->monthNames)) $this->generateCalendarArrays();

			// get the week interval - TODO: $GET
			$datestart=getdate(mktime(0,0,0,$month,$day,$year));
			while($datestart["wday"]!=$this->firstdayofweek){
				$datestart=getdate(mktime(0,0,0,$datestart["mon"],$datestart["mday"]-1,$datestart["year"]));
			}

			$datestop=getdate(mktime(23,59,59,$month,$day,$year));
			if ($datestop["wday"]==$this->firstdayofweek){
				$datestop=getdate(mktime(23,59,59,$datestop["mon"],$datestop["mday"]+1,$datestop["year"]));
			}
			while($datestop["wday"]!=$this->firstdayofweek){
				$datestop=getdate(mktime(23,59,59,$datestop["mon"],$datestop["mday"]+1,$datestop["year"]));
			}
			$datestop=getdate(mktime(23,59,59,$datestop["mon"],$datestop["mday"]-1,$datestop["year"]));

			$starttime=mktime(0,0,0,$datestart["mon"],$datestart["mday"],$datestart["year"]);
			$stoptime=mktime(23,59,59,$datestop["mon"],$datestop["mday"],$datestop["year"]);

			$today = getdate(time());
			$events = getEventsInInterval($starttime,$stoptime);

			$this->contentHeading(getMLText("week_view").": ".getReadableDate(mktime(12, 0, 0, $month, $day, $year)));

			echo "<div class=\"pagination pagination-small\">";
			echo "<ul>";
			print "<li><a href=\"../out/out.Calendar.php?mode=w&year=".($year)."&month=".($month)."&day=".($day-7)."\"><img src=\"".$this->getImgPath("m.png")."\" border=0></a></li>";
			print "<li><a href=\"../out/out.Calendar.php?mode=w\"><img src=\"".$this->getImgPath("c.png")."\" border=0></a></li>";
			print "<li><a href=\"../out/out.Calendar.php?mode=w&year=".($year)."&month=".($month)."&day=".($day+7)."\"><img src=\"".$this->getImgPath("p.png")."\" border=0></a></li>";
			echo "</ul>";
			echo "</div>";
			$this->contentContainerStart();

			echo "<table class='table-condensed'>\n";

			for ($i=$starttime; $i<$stoptime; $i += 86400){

				$date = getdate($i);

				// for daylight saving time TODO: could be better
				if ( ($i!=$starttime) && ($prev_day==$date["mday"]) ){
					$i += 3600;
					$date = getdate($i);
				}

				// highlight today
				$class = ($date["year"] == $today["year"] && $date["mon"] == $today["mon"] && $date["mday"]  == $today["mday"]) ? "todayHeader" : "header";

				echo "<tr>";
				echo "<td class='".$class."'>".getReadableDate($i)."</td>";
				echo "<td class='".$class."'>".$this->dayNamesLong[$date["wday"]]."</td>";

				if ($class=="todayHeader") $class="today";
				else $class="";

				foreach ($events as $event){
					if (($event["start"]<=$i)&&($event["stop"]>=$i)){
						print "<td class='".$class."'><a href=\"../out/out.ViewEvent.php?id=".$event['id']."\">".htmlspecialchars($event['name'])."</a></td>";
					}else{
						print "<td class='".$class."'>&nbsp;</td>";
					}
				}

				echo "</tr>\n";

				$prev_day=$date["mday"];
			}
			echo "</table>\n";

			$this->contentContainerEnd();
		}

		$this->contentEnd();
		$this->htmlEndPage();

	} /* }}} */
}
?>
