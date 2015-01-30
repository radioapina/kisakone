<?php
/**
 * Suomen Frisbeegolfliitto Kisakone
 * Copyright 2009-2010 Kisakone projektiryhm�
 * Copyright 2013-2015 Tuomo Tanskanen <tuomo@tanskanen.org>
 *
 * Data access module. Access the database server directly.
 *
 * --
 *
 * This file is part of Kisakone.
 * Kisakone is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Kisakone is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with Kisakone.  If not, see <http://www.gnu.org/licenses/>.
 * */

require_once 'data/db_init.php';


// Gets the user id for the username
// Returns null if the user was not found
function GetUserId($username)
{
   if (empty($username))
      return null;

   $retValue = null;
   $uname = escape_string($username);

   $query = format_query("SELECT id FROM :User WHERE Username = '$uname'");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = $row['id'];
   }
   mysql_free_result($result);

   return $retValue;
}


// Returns true if the user is a staff member in any tournament
function UserIsManagerAnywhere($userid)
{
   if (empty($userid))
      return null;

   $retValue = false;
   $userid = (int) $userid;

   $query = format_query("SELECT :User.id FROM :User
                         INNER JOIN :EventManagement ON :User.id = :EventManagement.User
                         WHERE :User.id = $userid AND (:EventManagement.Role = 'TD' OR :EventManagement.Role = 'Official')");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $retValue = mysql_num_rows($result) == 1;
   mysql_free_result($result);

   return $retValue;
}


// Returns a User object for the user whose email is $email
// Returns null if no user was found
function GetUserIdByEmail($email)
{
   if (empty($email))
      return null;

   $retValue = null;
   $email = escape_string($email);

   $query = format_query("SELECT id FROM :User WHERE UserEmail = '$email'");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = $row['id'];
   }
   mysql_free_result($result);

   return $retValue;
}


// Returns an array of User objects
function GetUsers($searchQuery = '', $sortOrder = '')
{
   $retValue = array();

   $query = "SELECT :User.id, Username, UserEmail, Role, UserFirstname, UserLastname, :User.Player,
                    :Player.lastname pLN, :Player.firstname pFN, :Player.email pEM
             FROM :User
             LEFT JOIN :Player ON :User.Player = :Player.player_id";
   $query .= " WHERE %s ";

   if ($sortOrder)
      $query .= " ORDER BY " . data_CreateSortOrder($sortOrder,
         array('name' => array('UserLastname', 'UserFirstname'), 'UserFirstname', 'UserLastname', 'pdga', 'Username' ));
   else
      $query .= " ORDER BY Username";

   $query = format_query($query,
      data_ProduceSearchConditions($searchQuery,
         array('UserFirstname', 'UserLastname', 'Username', ':Player.lastname', ':Player.firstname')));

   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new User($row['id'], $row['Username'], $row['Role'],
                          data_GetOne( $row['UserFirstname'], $row['pFN']),
                          data_GetOne( $row['UserLastname'], $row['pLN']),
                          data_GetOne( $row['UserEmail'], $row['pEM']), $row['Player']
                          );
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Returns an array of User objects for users who are also Players
// (optionally filtered by search conditions provided in $query)
function GetPlayerUsers($query = '', $sortOrder = '', $with_pdga_number = true)
{
   $retValue = array();

   if ($with_pdga_number)
      $searchConditions = data_ProduceSearchConditions($query, array('Username', 'pdga', 'UserFirstname', 'UserLastname'));
   else
      $searchConditions = data_ProduceSearchConditions($query, array('Username', 'UserFirstname', 'UserLastname'));

   $query = format_query("SELECT :User.id, Username, UserEmail, Role, UserFirstname, UserLastname, Player
      FROM :User
      INNER JOIN :Player ON :Player.player_id = :User.Player
      WHERE :User.Player IS NOT NULL AND %s", $searchConditions);

   if ($sortOrder)
      $query .= " ORDER BY " . data_CreateSortOrder($sortOrder, array('name' => array('UserLastname', 'UserFirstname'), 'UserFirstname', 'UserLastname', 'pdga', 'Username' ));
   else
     $query .= " ORDER BY Username";

   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new User($row['id'], $row['Username'], $row['Role'], $row['UserFirstname'], $row['UserLastname'], $row['UserEmail'], $row['Player']);
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a User object by the PDGA number of the associated Player
// Returns null if no user was found
function GetUsersByPdga($pdga)
{
   $pdga = (int) $pdga;
   $retValue = array();

   $query = format_query("SELECT :User.id, Username, UserEmail, Role, UserFirstname, UserLastname,
                           :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM
                         FROM :User
                         INNER JOIN :Player ON :Player.player_id = :User.Player WHERE :Player.pdga = '$pdga'");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new User($row['id'],
                        $row['Username'],
                        $row['Role'],
                        data_GetOne($row['UserFirstname'], $row['pFN']),
                        data_GetOne($row['UserLastname'], $row['pLN']),
                        data_GetOne($row['UserEmail'], $row['pEM']));
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a User object by the id number
// Returns null if no user was found
function GetUserDetails($userid)
{
   if (empty($userid))
      return null;

   $retValue = null;
   $id = (int) $userid;

   $query = format_query("SELECT :User.id, Username, UserEmail, Role, UserFirstname, UserLastname,
                                    :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM,
                                    :User.Player
                                    FROM :User
                                    LEFT JOIN :Player on :Player.player_id = :User.Player
                                    WHERE id = $id");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new User($row['id'], $row['Username'], $row['Role'], data_GetOne($row['UserFirstname'], $row['pFN']), data_GetOne($row['UserLastname'], $row['pLN']), data_GetOne($row['UserEmail'], $row['pEM']), $row['Player']);
   }

   mysql_free_result($result);

   return $retValue;
}


// Gets a Player object by id or null if the player was not found
function GetPlayerDetails($playerid)
{
   if (empty($playerid))
      return null;

   $retValue = null;
   $id = (int) $playerid;

   $query = format_query("SELECT player_id id, pdga PDGANumber, sex Sex, YEAR(birthdate) YearOfBirth
        FROM :Player
        WHERE player_id = $id");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Player($row['id'], $row['PDGANumber'], $row['Sex'], $row['YearOfBirth']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a User object associated with Playerid
function GetPlayerUser($playerid = null)
{
   if ($playerid === null)
      return null;

   $playerid = (int) $playerid;
   $query = format_query("SELECT :User.id, Username, UserEmail, Role, UserFirstname, UserLastname,
                            :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM
                         FROM :User
                         INNER JOIN :Player ON :Player.player_id = :User.Player WHERE :Player.player_id = '$playerid'");
   $result = execute_query($query);

   if (mysql_num_rows($result) === 1) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new User($row['id'],
                        $row['Username'],
                        $row['Role'],
                        data_GetOne($row['UserFirstname'], $row['pFN']),
                        data_GetOne($row['UserLastname'], $row['pLN']),
                        data_GetOne($row['UserEmail'], $row['pEM']),
                        $playerid);

         return $temp;
      }
   }
   mysql_free_result($result);

   return null;
}


// Gets a Player object for the User by userid or null if the player was not found
function GetUserPlayer($userid)
{
   if (empty($userid))
      return null;

   require_once 'core/player.php';

   $retValue = null;
   $id = (int) $userid;
   $query = format_query("SELECT :Player.player_id id, pdga PDGANumber, sex Sex, YEAR(birthdate) YearOfBirth, firstname, lastname, email
                                      FROM :Player INNER JOIN :User ON :User.Player = :Player.player_id
                                      WHERE :User.id = $id");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Player($row['id'],
                           $row['PDGANumber'],
                           $row['Sex'],
                           $row['YearOfBirth'],
                           $row['firstname'],
                           $row['lastname'],
                           $row['email']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an array of Event objects where the conditions match
function data_GetEvents($conditions, $sort_mode = null)
{
   $retValue = array();

   global $event_sort_mode;
   if ($sort_mode !== null) {
      $sort = "`$sort_mode`";
   }
   elseif (!$event_sort_mode) {
     $sort = "`Date`";
   }
   else {
     $sort = data_CreateSortOrder($event_sort_mode, array('Name', 'VenueName' => 'Venue', 'Date', 'LevelName'));
   }

   global $user;
   if ($user && $user->id) {
      $uid = $user->id;

      $player = $user->GetPlayer();
      if (is_a($player, 'Error'))
         return $player;
      $playerid = $player ? $player->id : -1;

      $query = format_query("SELECT :Event.id, :Venue.Name AS Venue, :Venue.id AS VenueID, Tournament,
            Level, :Event.Name, UNIX_TIMESTAMP(Date) Date, Duration,
            UNIX_TIMESTAMP(ActivationDate) ActivationDate, UNIX_TIMESTAMP(SignupStart) SignupStart,
            UNIX_TIMESTAMP(SignupEnd) SignupEnd, ResultsLocked,
            :Level.Name LevelName, :EventManagement.Role AS Management, :Participation.Approved,
            :Participation.EventFeePaid, :Participation.Standing
        FROM :Event
        LEFT JOIN :EventManagement ON (:Event.id = :EventManagement.Event AND :EventManagement.User = $uid)
        LEFT JOIN :Participation ON (:Participation.Event = :Event.id AND :Participation.Player = $playerid)
        LEFT JOIN :Level ON :Event.Level = :Level.id
        INNER Join :Venue ON :Venue.id = :Event.Venue
        WHERE $conditions
        ORDER BY %s", $sort);
   }
   else {
      $query = format_query("SELECT :Event.id, :Venue.Name AS Venue, :Venue.id AS VenueID, Tournament,
            Level, :Event.Name, UNIX_TIMESTAMP(Date) Date, Duration,
            UNIX_TIMESTAMP(ActivationDate) ActivationDate, UNIX_TIMESTAMP(SignupStart) SignupStart,
            UNIX_TIMESTAMP(SignupEnd) SignupEnd, ResultsLocked,
            :Level.Name LevelName
        FROM :Event
        INNER Join :Venue ON :Venue.id = :Event.Venue
        LEFT JOIN :Level ON :Event.Level = :Level.id
        WHERE $conditions
        ORDER BY %s", $sort);
   }
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new Event($row);
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets events for a specific tournament
function GetTournamentEvents($tournamentId)
{
   $tournamentId = (int) $tournamentId;
   $conditions = ":Event.Tournament = $tournamentId";
   return data_GetEvents($conditions);
}


// Gets the number of people who have signed up for a tournament
function GetTournamentParticipantCount($tournamentId)
{
   $retValue = null;
   $tournamentId = (int) $tournamentId;

   $query = format_query("SELECT COUNT(DISTINCT :Participation.Player) Count FROM :Event
                  INNER JOIN :Participation ON :Participation.Event = :Event.id
                  WHERE :Event.Tournament = $tournamentId AND :Participation.EventFeePaid IS NOT NULL");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $temp = mysql_fetch_assoc($result);
      $retValue = $temp['Count'];
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an Event object by ID or null if the event was not found
function GetEventDetails($eventid)
{
   if (empty($eventid))
      return null;

   $retValue = null;
   $id = (int) $eventid;

   global $user;
   if ($user && $user->id) {
      $uid = $user->id;

      $player = $user->GetPlayer();
      $pid = $player ? $player->id : -1;

      $query = format_query("SELECT DISTINCT :Event.id, :Venue.Name AS Venue, :Venue.id AS VenueID, Tournament, AdBanner, :Event.Name, ContactInfo,
                                      UNIX_TIMESTAMP(Date) Date, Duration, PlayerLimit, UNIX_TIMESTAMP(ActivationDate) ActivationDate, UNIX_TIMESTAMP(SignupStart) SignupStart,
                                      UNIX_TIMESTAMP(SignupEnd) SignupEnd, ResultsLocked, PdgaEventId,
                                      :EventManagement.Role AS Management, :Participation.Approved, :Participation.EventFeePaid, :Participation.Standing, :Level.id LevelId,
                                      :Level.Name Level, :Tournament.id TournamentId, :Tournament.Name Tournament, :Participation.SignupTimestamp
                                      FROM :Event
                                      LEFT JOIN :EventManagement ON (:Event.id = :EventManagement.Event AND :EventManagement.User = $uid)
                                      LEFT JOIN :Participation ON (:Participation.Event = :Event.id AND :Participation.Player = $pid)
                                      LEFT Join :Venue ON :Venue.id = :Event.Venue
                                      LEFT JOIN :Level ON :Level.id = :Event.Level
                                      LEFT JOIN :Tournament ON :Tournament.id = :Event.Tournament
                                      WHERE :Event.id = $id ");
   }
   else {
      $query = format_query("SELECT DISTINCT :Event.id id, :Venue.Name AS Venue, Tournament, AdBanner, :Event.Name, UNIX_TIMESTAMP(Date) Date, Duration, PlayerLimit, UNIX_TIMESTAMP(ActivationDate) ActivationDate, ContactInfo,
                            UNIX_TIMESTAMP(SignupStart) SignupStart, UNIX_TIMESTAMP(SignupEnd) SignupEnd, ResultsLocked, PdgaEventId, :Level.id LevelId, :Level.Name Level,
                            :Tournament.id TournamentId, :Tournament.Name Tournament
                                      FROM :Event
                                      LEFT JOIN :Level ON :Level.id = :Event.Level
                                      LEFT JOIN :Tournament ON :Tournament.id = :Event.Tournament
                                      LEFT Join :Venue ON :Venue.id = :Event.Venue
                                      WHERE :Event.id = $id ");
   }
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Event($row);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an array of strings containing Venue names that match the searchQuery
function GetVenueNames($searchQuery = '')
{
   $retValue = array();
   $query = "SELECT DISTINCT Name FROM :Venue";
   $query .= " WHERE %s";
   $query .= " ORDER BY Name";
   $query = format_query($query, data_ProduceSearchConditions($searchQuery, array('Name')));
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row['Name'];
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an array of Tournament objects for a specific year
function GetTournaments($year, $onlyAvailable = false)
{
   if ($year && ($year < 2000 || $year > 2100))
      return Error::InternalError();

   require_once 'core/tournament.php';
   $retValue = array();

   $query = format_query("SELECT id, Level, Name, ScoreCalculationMethod, Year, Available FROM :Tournament WHERE 1 ");
   if ($year) {
      $year = (int) $year;
      $query .= " AND Year = $year ";
   }
   if ($onlyAvailable) {
      $query .= " AND Available <> 0";
   }
   $query .= " ORDER BY Year, Name";

   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = new Tournament($row['id'], $row['Level'], $row['Name'], $row['Year'], $row['ScoreCalculationMethod'], $row['Available']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an array of Level objects (optionally filtered by the Available bit)
function GetLevels($availableOnly = false)
{
   require_once 'core/level.php';

   $retValue = array();
   $query = "SELECT id, Name, ScoreCalculationMethod, Available FROM :Level";

   if ($availableOnly)
      $query .= " WHERE Available <> 0";
   $query = format_query($query);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = new Level($row['id'], $row['Name'], $row['ScoreCalculationMethod'], $row['Available']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a Level object by id
function GetLevelDetails($levelId)
{
   require_once 'core/level.php';

   $retValue = array();
   $levelId = (int) $levelId;

   $query = format_query("SELECT id, Name, ScoreCalculationMethod, Available FROM :Level WHERE id = $levelId");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Level($row['id'], $row['Name'], $row['ScoreCalculationMethod'], $row['Available']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets an array of Classification objects (optionally filtered by the Available bit)
function GetClasses($onlyAvailable = false)
{
   require_once 'core/classification.php';

   $retValue = array();

   $query = "SELECT id, Name, MinimumAge, MaximumAge, GenderRequirement, Available FROM :Classification";
   if ($onlyAvailable)
      $query .= " WHERE Available <> 0";
   $query .= " ORDER BY Name";
   $query = format_query($query);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = new Classification($row['id'], $row['Name'], $row['MinimumAge'],
                                                   $row['MaximumAge'], $row['GenderRequirement'], $row['Available']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a Classification object by id
function GetClassDetails($classId)
{
   require_once 'core/classification.php';

   $retValue = null;
   $classId = (int) $classId;

   $query = format_query("SELECT id, Name, MinimumAge, MaximumAge, GenderRequirement, Available FROM :Classification WHERE id = $classId");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Classification($row['id'], $row['Name'], $row['MinimumAge'],
                                                   $row['MaximumAge'], $row['GenderRequirement'], $row['Available']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Gets a Section object by id
function GetSectionDetails($sectionId)
{
   require_once 'core/section.php';

   $retValue = null;
   $sectionId = (int) $sectionId;

   $query = format_query("SELECT id, Name, Round, Priority, UNIX_TIMESTAMP(StartTime) StartTime, Present, Classification FROM :Section WHERE id = $sectionId");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Section($row);
   }
   mysql_free_result($result);

   return $retValue;
}


/**
 * Function for creating a new event
 *
 * Returns the new event id for success or
 * an Error in case there was an error in creating a new event.
 */
function CreateEvent($name, $venue, $duration, $playerlimit, $contact, $tournament, $level, $start,
                      $signup_start, $signup_end, $classes, $td, $officials, $requireFees, $pdgaId)
{
    $retValue = null;

    $query = format_query( "INSERT INTO :Event (Venue, Tournament, Level, Name, Date, Duration, PlayerLimit, SignupStart, SignupEnd, ContactInfo, FeesRequired, PdgaEventId) VALUES
                     (%d, %d, %d, '%s', FROM_UNIXTIME(%d), %d, %d, FROM_UNIXTIME(%s), FROM_UNIXTIME(%s), '%s', %d)",
                      esc_or_null($venue, 'int'), esc_or_null($tournament, 'int'), esc_or_null($level, 'int'), mysql_real_escape_string($name),
                      (int) $start, (int) $duration, (int) $playerlimit,
                      esc_or_null($signup_start, 'int'), esc_or_null($signup_end,'int'), mysql_escape_string($contact),
                      $requireFees, $pdgaId);
    $result = execute_query($query);

    if ($result) {
        $eventid = mysql_insert_id();
        $retValue = $eventid;

        $retValue = SetClasses($eventid, $classes);
        if (!is_a($retValue, 'Error')) {
            $retValue = SetTD($eventid, $td);
            if (!is_a($retValue, 'Error'))
                $retValue = SetOfficials($eventid, $officials);
        }
    }
    else
        return Error::Query($query, 'CreateEvent');

    if (!is_a($retValue, 'Error'))
      $retValue = $eventid;

    return $retValue;
}


// Edits users user and player information
function EditUserInfo($userid, $email, $firstname, $lastname, $gender, $pdga, $dobyear)
{

   $query = format_query("UPDATE :User SET UserEmail = %s, UserFirstName = %s, UserLastName = %s WHERE id = %d",
                                   esc_or_null($email), esc_or_null(data_fixNameCase($firstname)), esc_or_null(data_fixNameCase($lastname)), (int) $userid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($user_query);

   $u = GetUserDetails($userid);
   $player = $u->GetPlayer();
   if ($player) {
      $playerid = $player->id;

      $query = format_query("UPDATE :Player SET sex = %s, pdga = %s,
                              birthdate = '%s', firstname = %s, lastname = %s,
                              email = %s
                              WHERE player_id = %d",
                                    strtoupper($gender) == 'M' ? "'male'" : "'female'", esc_or_null($pdga, 'int'), (int) $dobyear . '-1-1',
                                    esc_or_null(data_fixNameCase($firstname)), esc_or_null(data_fixNameCase($lastname)), esc_or_null($email),
                                    (int) $playerid);
      $result = execute_query($query);

      if (!$result)
         return Error::Query($plr_query);
   }
}


// Gets Events by date
function GetEventsByDate($start, $end)
{
   $start = (int) $start;
   $end = (int) $end;
   return  data_GetEvents("Date BETWEEN FROM_UNIXTIME($start) AND FROM_UNIXTIME($end)");
}


// Get all Classifications in an Event
function GetEventClasses($event)
{
   require_once 'core/classification.php';

   $retValue = array();
   $event = (int) $event;
   $query = format_query("SELECT :Classification.id, Name, MinimumAge, MaximumAge, GenderRequirement, Available
                  FROM :Classification, :ClassInEvent
                  WHERE :ClassInEvent.Classification = :Classification.id AND
                        :ClassInEvent.Event = $event
                        ORDER BY Name");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = new Classification($row);
   }
   mysql_free_result($result);

   return $retValue;
}


/* Get Quotas for Classes in Event */
function GetEventQuotas($eventId)
{
   $retValue = array();
   $event = (int) $eventId;

   // All classes as assoc array
   $query = format_query("SELECT :Classification.id, Name, :ClassInEvent.MinQuota, :ClassInEvent.MaxQuota
                  FROM :Classification, :ClassInEvent
                  WHERE :ClassInEvent.Classification = :Classification.id AND
                        :ClassInEvent.Event = $eventId
                        ORDER BY Name");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row;
   }
   mysql_free_result($result);

   return $retValue;
}


// Return min and max quota for a class
function GetEventClassQuota($eventid, $classid)
{
   $quotas = GetEventQuotas($eventid);
   foreach ($quotas as $quota) {
      if ($quota['id'] == $classid)
         return array($quota['MinQuota'], $quota['MaxQuota']);
   }

   // not found, give defaults
   return array(0, 999);
}


// Set class's min quota
function SetEventClassMinQuota($eventid, $classid, $quota)
{
   $query = format_query("UPDATE :ClassInEvent SET MinQuota = %d WHERE Event = %d AND Classification = %d",
                 $quota, $eventid, $classid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_affected_rows() == 1;
}


// Set class's max quota
function SetEventClassMaxQuota($eventid, $classid, $quota)
{
   $query = format_query("UPDATE :ClassInEvent SET MaxQuota = %d WHERE Event = %d AND Classification = %d",
                 $quota, $eventid, $classid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_affected_rows() == 1;
}


// Get sections for a Round
function GetSections($round, $order = 'time')
{
   require_once 'core/section.php';

   $retValue = array();
   $roundId = (int) $round;
   $query = "SELECT :Section.id,  Name,
                         UNIX_TIMESTAMP(StartTime) StartTime, Priority, Classification, Round, Present
                                      FROM :Section
                                      WHERE :Section.Round = $roundId ORDER BY ";

   if ($order == 'time')
      $query .= "Priority, StartTime, Name";
   else
      $query .= "Classification, Name";
   $query = format_query($query);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = new Section($row);
   }
   mysql_free_result($result);

   return $retValue;
}


// Get rounds for an event by event id
function GetEventRounds($event)
{
   require_once 'core/round.php';

   $retValue = array();
   $event = (int) $event;
   $query = format_query("SELECT id, Event, Course, StartType,UNIX_TIMESTAMP(StartTime) StartTime,
                         `Interval`, ValidResults, GroupsFinished FROM :Round WHERE Event = $event ORDER BY StartTime");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      while ($row = mysql_fetch_assoc($result)) {
         $newRound =  new Round($row['id'], $row['Event'], $row['StartType'], $row['StartTime'], $row['Interval'], $row['ValidResults'], 0, $row['Course'], $row['GroupsFinished']);
         $newRound->roundnumber = $index++;
         $retValue[] = $newRound;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Get a Round object by id
function GetRoundDetails($roundid)
{
   require_once 'core/round.php';

   $retValue = null;
   $id = (int) $roundid;
   $query = format_query("SELECT
      id, Event, Course, StartType,UNIX_TIMESTAMP(StartTime) StartTime, `Interval`, ValidResults, GroupsFinished
      FROM `:Round` WHERE id = $id ORDER BY StartTime");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      while ($row = mysql_fetch_assoc($result))
         $retValue =  new Round($row['id'], $row['Event'], $row['StartType'], $row['StartTime'], $row['Interval'], $row['ValidResults'], 0, $row['Course'], $row['GroupsFinished']);
   }
   mysql_free_result($result);

   return $retValue;
}


// Get event officials for an event
function GetEventOfficials($event)
{
   require_once 'core/event_official.php';

   $retValue = array();
   $event = (int) $event;
   $query = format_query("SELECT :User.id as UserId, Username, UserEmail, :EventManagement.Role, UserFirstname, UserLastname, Event ,
                                    :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM, Player
                                    FROM :EventManagement, :User
                                    LEFT JOIN :Player ON :User.Player = :Player.player_id
                                    WHERE :EventManagement.User = :User.id AND :EventManagement.Event = $event
                                    ORDER BY :EventManagement.Role DESC, Username ASC");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $tempuser = new User($row['UserId'],
                              $row['Username'],
                              $row['Role'],
                              data_GetOne( $row['UserFirstname'], $row['pFN']),
                              data_GetOne($row['UserLastname'], $row['pLN']),
                              data_GetOne($row['UserEmail'], $row['pEM']),
                              $row['Player']);

         $retValue[] = new EventOfficial($row['UserId'], $row['Event'], $tempuser, $row['Role']);
      }
   }
   mysql_free_result($result);

   return $retValue;
}


// Edit event information
function EditEvent($eventid, $name, $venuename, $duration, $playerlimit, $contact, $tournament, $level, $start, $signup_start, $signup_end, $state, $requireFees, $pdgaId)
 {
   $venueid = GetVenueId($venuename);

   $activation = ($state == 'active' || $state =='done') ? time() : 'NULL';
   $locking = ($state =='done') ? time() : 'NULL';
   $query = format_query("UPDATE `:Event` SET `Venue` = %d, `Tournament` = %s, Level = %d, `Name` = '%s', `Date` = FROM_UNIXTIME(%d),
                    `Duration` = %d, `PlayerLimit` = %d, `SignupStart` = FROM_UNIXTIME(%s), `SignupEnd` = FROM_UNIXTIME(%s),
                    ActivationDate = FROM_UNIXTIME( %s), ResultsLocked = FROM_UNIXTIME(%s), ContactInfo = '%s', FeesRequired = %d, PdgaEventId = %d
                    WHERE id = %d",
                            $venueid,
                            esc_or_null($tournament, 'int'), $level, mysql_real_escape_string($name), (int) $start,
                            (int) $duration, (int) $playerlimit,
                            esc_or_null($signup_start,'int'), esc_or_null($signup_end,'int'), $activation,
                            $locking,
                            mysql_real_escape_string($contact), $requireFees, $pdgaId, (int) $eventid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


/**
 * Function for setting the tournament director for en event
 *
 * Returns null for success or
 * an Error in case there was an error in setting the TD.
 */
function SetTD($eventid, $td)
{
   $retValue = Null;

   if (isset($eventid) and isset($td)) {
      $eventid  = (int) $eventid;
      $query = format_query("DELETE FROM :EventManagement WHERE Event = $eventid AND Role = 'td'");
      execute_query($query);

      $query = format_query( "INSERT INTO :EventManagement (User, Event, Role) VALUES (%d, %d, '%s');",
                          (int) $td, (int) $eventid, 'td');
      $result = execute_query($query);

      if (!$result)
         return Error::Query($query);
   }
   else
      return Error::internalError("Event id or td argument is not set.");

   return $retValue;
}


/**
 * Function for setting the officials for en event
 *
 * Returns null for success or
 * an Error in case there was an error in setting the official.
 */
function SetOfficials($eventid, $officials)
{
   $eventid = (int) $eventid;
   $retValue = null;

   if (isset($eventid)) {
      $query = format_query("DELETE FROM :EventManagement WHERE Event = %d AND Role = 'official'", $eventid);
      execute_query($query);

      foreach ($officials as $official) {
         $query = format_query("INSERT INTO :EventManagement (User, Event, Role) VALUES (%d, %d, '%s');",
                           (int) $official, (int) $eventid, 'official');
         $result = execute_query($query);

         if (!$result)
            return Error::Query($query);
      }
   }
   else
      return Error::internalError("Event id argument is not set.");

   return $retValue;
}


/**
 * Function for setting the classes for en event
 *
 * Returns null for success or
 * an Error in case there was an error in setting the class.
 */
function SetClasses($eventid, $classes)
{
   $retValue = null;
   $eventid = (int) $eventid;

   if (isset($eventid)) {
      $quotas = GetEventQuotas($eventid);
      $query = format_query("DELETE FROM :ClassInEvent WHERE Event = $eventid");
      execute_query($query);

      foreach ($classes as $class) {
         $query = format_query("INSERT INTO :ClassInEvent (Classification, Event) VALUES (%d, %d);",
                           (int) $class, (int) $eventid);
         $result = execute_query($query);

         if (!$result)
            return Error::Query($query);
      }

      // Fix limits back.. do not bother handling errors as some classes may be removed
      foreach ($quotas as $quota) {
         $cid = (int) $quota['id'];
         $min = (int) $quota['MinQuota'];
         $max = (int) $quota['MaxQuota'];

         $query = format_query("UPDATE :ClassInEvent SET MinQuota = %d, MaxQuota = %d
                                 WHERE Event = %d AND Classification = %d",
                                 $min, $max, $eventid, $cid);
         execute_query($query);
      }
   }
   else
      return Error::internalError("Event id argument is not set.");

   return $retValue;
}


/**
 * Function for setting the rounds for en event
 *
 * Returns null for success or
 * an Error in case there was an error in setting the round.
 */
function SetRounds( $eventid, $rounds, $deleteRounds = array())
{
   $retValue = null;
   $eventid = (int) $eventid;
   foreach ($deleteRounds as $toDelete) {
      $toDelete = (int) $toDelete;
      $query = format_query("DELETE FROM :Round WHERE Event = $eventid AND id = $toDelete");
      execute_query($query);
   }

   foreach ($rounds as $round) {
      $date = $round['date'];
      $time = $round['time'];
      $datestring = $round['datestring'];
      $roundid = $round['roundid'];

      $r_event = (int) $eventid;
      $r_course = null;
      $r_starttype = "simultaneous";
      $r_starttime = (int) $date;
      $r_interval = 10;
      $r_validresults = 1;

      if (empty($roundid) || $roundid == '*') {
         $query = format_query("INSERT INTO :Round (Event, Course, StartType, StartTime, `Interval`, ValidResults) VALUES (%d, %s, '%s', FROM_UNIXTIME(%d), %d, %d);",
                           $r_event, esc_or_null($r_course, 'int'), $r_starttype, $r_starttime, $r_interval, $r_validresults);
         $result = execute_query($query);

         if (!$result)
            return Error::Query($query);

         $roundid = mysql_insert_id();
      }
   }

   return $retValue;
}


/**
 * Function for setting the round course
 *
 * Returns cource id for success or an Error
 */
function GetOrSetRoundCourse($roundid)
{
   $courseid = null;
   $query = format_query("SELECT Course FROM :Round WHERE id = %d",
                      (int) $roundid);
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $course = $row['Course'];
      mysql_free_result($result);
   }
   else
      return Error::internalError("Invalid round id argument");

   // Create a new course for the round
   $query = format_query("INSERT INTO :Course (Venue, Name, Description, Link, Map) VALUES (NULL, '%s', '%s', '%s', '%s');",
                     "", "", "", "");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $courseid = mysql_insert_id();
   $query = format_query("UPDATE :Round SET Course = %d WHERE id = %d;", $courseid, $roundid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

  return $courseid;
}


/** ****************************************************************************
 * Function for checking if player fits event quota or should be queued
 *
 * Returns true for direct signup, false for queue
 *
 * @param int  $eventId   Event ID
 * @param int  $playerId  Player ID
 * @param int  $classId   Classification ID
 */
function CheckSignUpQuota($eventId, $playerId, $classId)
{
   $event = GetEventDetails($eventId);
   $participants = $event->GetParticipants();
   $limit = $event->playerLimit;
   $total = count($participants);

   // Too many players registered already
   if ($limit > 0 && $total >= $limit)
      return false;

   // Calculate some limits and counts
   list($minquota, $maxquota) = GetEventClassQuota($eventId, $classId);
   $classcounts = GetEventParticipantCounts($eventId);
   $classcount = isset($classcounts[$classId]) ? $classcounts[$classId] : 0;

   // Check versus class maxquota
   if ($classcount >= $maxquota)
      return false;

   // If there is unused quota in class, allow player in
   if ($classcount < $minquota)
      return true;

   // Calculate unused quota in other divisions, if there is global limit set
   if ($limit > 0) {
      $unusedQuota = 0;
      $quotas = GetEventQuotas($eventId);

      foreach ($quotas as $idx => $quota) {
         $cquota = $quota['MinQuota'];
         $ccount = (isset($classcounts[$quota['id']]) ? $classcounts[$quota['id']] : 0);
         $cunused = $cquota - $ccount;

         if ($cunused > 0)
            $unusedQuota += $cunused;
      }
      $spots_left = $limit - $total - $unusedQuota;

      // Deny if there is no unreserved space left
      if ($spots_left <= 0)
         return false;
   }

   // ok, we have space left
   return true;
}


/**
 * Function for setting the user participation on an event
 *
 * Returns true for success, false for successful queue signup or an Error
 */
function SetPlayerParticipation($playerid, $eventid, $classid, $signup_directly = true)
{
   $retValue = $signup_directly;

   $table = ($signup_directly === true) ? "Participation" : "EventQueue";

   // Inputmapping is already checking player's re-entry, so this is merely a cleanup from queue
   // and double checking that player will not be in competition table twice
   CancelSignup($eventid, $playerid, false);

   $query = format_query("INSERT INTO :$table (Player, Event, Classification) VALUES (%d, %d, %d);",
                         (int) $playerid, (int) $eventid, (int) $classid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return $retValue;
}


// Check if we can raise players from queue after someone left
function CheckQueueForPromotions($eventId)
{
   $queuers = GetEventQueue($eventId, '', '');
   foreach ($queuers as $queuer) {
      $playerId = $queuer['player']->id;
      $classId = $queuer['classId'];

      if (CheckSignupQuota($eventId, $playerId, $classId)) {
         $retVal = PromotePlayerFromQueue($eventId, $playerId);
         if (is_a($retVal, 'Error'))
            error_log("Error promoting player $playerId to event $eventId at class $classId");
      }
   }

   return null;
}


// Raise competitor from queue to the event
function PromotePlayerFromQueue($eventId, $playerId)
{
   // Get data from queue
   $query = format_query("SELECT * FROM :EventQueue WHERE Player = $playerId AND Event = $eventId");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $row = mysql_fetch_assoc($result);

      // Insert into competition
      $query = format_query("INSERT INTO :Participation (Player, Event, Classification, SignupTimestamp) VALUES (%d, %d, %d, FROM_UNIXTIME(%d));",
                         (int) $row['Player'], (int) $row['Event'], (int) $row['Classification'], time());
      $result = execute_query($query);

      if (!$result)
         return Error::Query($query);

      // Remove data from queue
      $query = format_query("DELETE FROM :EventQueue WHERE Player = $playerId AND Event = $eventId");
      $result = execute_query($query);

      if (!$result)
         return Error::Query($query);

      $user = GetPlayerUser($playerId);
      if ($user !== null) {
         require_once 'core/email.php';
         error_log("Sending email to ".print_r($user, true));
         SendEmail(EMAIL_PROMOTED_FROM_QUEUE, $user->id, GetEventDetails($eventId));
      }
      else
         error_log("Cannot send promotion email: user !== null failed, playerId = ".$playerId);
   }
   mysql_free_result($result);

   return null;
}


// Cancels a players signup for an event
function CancelSignup($eventId, $playerId, $check_promotion = true)
{
    // Delete from event and queue
    $query = format_query("DELETE FROM :Participation WHERE Player = $playerId AND Event = $eventId");
    execute_query($query);

    $query = format_query("DELETE FROM :EventQueue WHERE Player = $playerId AND Event = $eventId");
    execute_query($query);

    if ($check_promotion === false)
      return null;

    // Check if we can lift someone into competition
    return CheckQueueForPromotions($eventId);
}


/**
 * Function for setting the venue
 *
 * Returns venue id for success or an Error
 */
function GetVenueId($venue)
{
   $venueid = null;
   // Get the existing venue
   $query = format_query("SELECT id FROM :Venue WHERE Name = '%s'", mysql_real_escape_string( $venue));
   $result = execute_query($query);

   if (mysql_num_rows($result) >= 1) {
      $row = mysql_fetch_assoc($result);
      $venueid = $row['id'];
      mysql_free_result($result);
   }

   if (!isset($venueid)) {
      // Create a new venue
      $query = format_query("INSERT INTO :Venue (Name) VALUES ('%s')", mysql_real_escape_string( $venue));
      $result = execute_query($query);

      if (!$result)
         return Query::Error($query);

      $venueid = mysql_insert_id();
   }

   return $venueid;
}


function CreateNewsItem($eventid, $title, $text)
{
   $query = format_query("INSERT INTO :TextContent(Event, Title, Date, Content, Type) VALUES(%d, '%s', NOW(), '%s', 'news')",
                            (int) $eventid, mysql_real_escape_string($title), mysql_real_escape_string($text));
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function EditNewsItem($itemid, $title, $text)
{
   $query = format_query("UPDATE :TextContent SET Title = '%s', Content = '%s' WHERE id = %d",
                          mysql_real_escape_string($title), mysql_real_escape_string($text), (int) $itemid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


/**
 * Function for setting or changing the user data.
 *
 * @param class User $user - single system users personal data
 */

function SetUserDetails($user)
{
   $retValue = null;

   if (is_a($user,"User")) {
      $u_username_quoted = $user->username ? escape_string($user->username) : 'NULL';
      $u_email     = escape_string($user->email);
      $u_password  = $user->password;
      $u_hash      = $user->GetHashType();
      $u_salt      = $user->salt ? "'$user->salt'" : 'NULL';
      $u_role      = escape_string($user->role);
      $u_firstname = escape_string(data_fixNameCase($user->firstname));
      $u_lastname  = escape_string(data_fixNameCase($user->lastname));

      // Check that username is not already in use
      if (!GetUserId($user->username)) {
         // Username is unique, proceed to insert into table
         $query = format_query( "INSERT INTO :User (
                                 Username, UserEmail, Password, Role, UserFirstName, UserLastName, Player, Hash, Salt)
                                 VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %s, '%s', %s);",
                           $u_username_quoted, $u_email, $u_password, $u_role, $u_firstname, $u_lastname,
                           esc_or_null($user->player, 'int'), $u_hash, $u_salt);
         $result = execute_query($query);

         if (!$result)
            return Error::Query($query);

         // Get id for the new user
         $u_id = mysql_insert_id();
         $user->SetId( $u_id);
         $retValue = $user;
      }
      else {
         // Username already in use, report error
         // TODO: Maybe use some Error::<something>
         $err = new Error();
         $err->title = "error_invalid_argument";
         $err->description = translate("error_invalid_argument_description");
         $err->internalDescription = "Username is already in use";
         $err->function = "SetUserDetails()";
         $err->IsMajor = false;
         $err->data = "username:" . $u_username .
                      "; role:" . $u_role .
                      "; firstname:" . $u_firstname .
                      "; lastname:" . $u_lastname;
         $retValue = $err;
      }
   }
   else
      return Error::internalError("Wrong class as argument");

   return $retValue;
}


/**
 * Function for setting or changing the player data.
 *
 * @param class Player $player - single system users player data
 */

function SetPlayerDetails($player)
{
    $retValue = null;
    if ( is_a( $player, "Player")) {
        $dbError = InitializeDatabaseConnection();
        if ($dbError) {
           return $dbError;
        }

        $query = format_query( "INSERT INTO :Player (pdga, sex, lastname, firstname, birthdate, email) VALUES (
                            %s, '%s', %s, %s, '%s', %s
                            );",
                          esc_or_null($player->pdga),
                          $player->gender == 'M' ? 'male' : 'female',
                          esc_or_null(data_fixNameCase($player->lastname)),
                          esc_or_null(data_fixNameCase($player->firstname)),
                          (int) $player->birthyear . '-1-1',
                          esc_or_null($player->email));
        $result = execute_query($query);

        if (!$result)
            return Error::Query($query);

        if ($result) {
            $p_id = mysql_insert_id();
            $player->SetId($p_id);
            $retValue = $player;
        }
    }
    else
      return Error::internalError("Wrong class as argument");

   return $retValue;
}


function GetAllTextContent($eventid)
{
   require_once 'core/textcontent.php';

   $retValue = array();
   $eventCond = $eventid ? " = " . (int) $eventid : " IS NULL";
   $eventid = esc_or_null($eventid, 'int');
   $query = format_query("SELECT id, Event, Title, Content, UNIX_TIMESTAMP(Date)
                                    Date, Type, `Order`  FROM :TextContent
                                    WHERE Event $eventCond AND Type !=  'news' ORDER BY `order`");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new TextContent($row);
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetEventNews($eventid, $from, $count)
{
   require_once 'core/textcontent.php';

   $retValue = array();
   $eventid = (int) $eventid;
   $from = (int) $from;
   $count = (int) $count;

   $query = format_query("SELECT id, Event, Title, Content, UNIX_TIMESTAMP(Date) Date,
                                    Type, `Order`  FROM :TextContent
                                    WHERE Event = $eventid AND Type =  'news' ORDER BY `date` DESC
                                    LIMIT $from, $count");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new TextContent($row);
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetTextContent($pageid)
{
   if (empty($pageid))
      return null;

   $retValue = null;
   $id = (int) $pageid;
   $query = format_query("SELECT id, Event, Title, Content, Date, Type, `Order` FROM :TextContent WHERE id = $id ");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new TextContent($row);
   }
   mysql_free_result($result);

   return $retValue;
}


function GetTextContentByEvent($eventid, $type)
{
   $retValue = null;
   $id = (int) $eventid;
   $type = escape_string($type);
   $eventCond = $id ? "= $id" : "IS NULL";

   $query = format_query("SELECT id, Event, Title, Content, Date, Type, `Order` FROM :TextContent WHERE event $eventCond AND `type` = '$type' ");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $row = mysql_fetch_assoc($result);
      $retValue = new TextContent($row);
   }
   mysql_free_result($result);

   return $retValue;
}


function GetTextContentByTitle($eventid, $title)
{
   $retValue = null;
   $id = (int) $eventid;
   $title = mysql_real_escape_string($title);
   $eventCond = $id ? "= $id" : "IS NULL";

   $query = format_query("SELECT id, Event, Title, Content, Date, Type, `Order` FROM :TextContent WHERE event $eventCond AND `title` = '$title' ");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = new TextContent($row);
   }
   mysql_free_result($result);

   return $retValue;
}


function EditClass($id, $name, $minage, $maxage, $gender, $available)
{
   $query = format_query("UPDATE :Classification SET Name = '%s', MinimumAge = %s, MaximumAge = %s, GenderRequirement = %s, Available = %d
                           WHERE id = %d",
                    escape_string($name), esc_or_null($minage,'int'), esc_or_null($maxage, 'int'),
                    esc_or_null($gender, 'gender'), $available ? 1:0, $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function CreateClass($name, $minage, $maxage, $gender, $available)
{
   $query = format_query("INSERT INTO :Classification (Name, MinimumAge, MaximumAge, GenderRequirement, Available)
                  VALUES ('%s', %s, %s, %s, %d);",
                    escape_string($name), esc_or_null($minage, 'int'), esc_or_null($maxage, 'int'),
                    esc_or_null($gender, 'gender'), $available ? 1:0);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function DeleteClass($id)
{
   $query = format_query("DELETE FROM :Classification WHERE id = ". (int) $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


// Returns true if the provided class is being used in any event, false otherwise
function ClassBeingUsed($id)
{
   $retValue = true;
   $id = (int) $id;
   $query = format_query("SELECT COUNT(*) AS Events FROM :ClassInEvent WHERE Classification = %d"
                          , $id);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $temp = mysql_fetch_assoc($result);
      $retValue = ($temp['Events']) > 0;
   }
   mysql_free_result($result);

   return $retValue;
}


function EditLevel($id, $name, $method, $available)
{
   $query = format_query("UPDATE :Level SET Name = '%s', ScoreCalculationMethod = '%s', Available = %d WHERE id = %d",
                            escape_string($name), escape_string($method), $available ? 1 : 0, (int) $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function CreateLevel($name, $method, $available)
{
   $retValue = null;

   $query = format_query("INSERT INTO :Level (Name, ScoreCalculationmethod, Available) VALUES ('%s', '%s', %d)",
                      escape_string( $name), escape_string($method), $available ? 1 : 0);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_insert_id();
}


function DeleteLevel($id)
{
   $query = format_query("DELETE FROM :Level WHERE id = ". (int) $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


// Returns true if the provided level is being used in any event or tournament, false otherwise
function LevelBeingUsed($id)
{
   $retValue = true;
   $id = (int) $id;

   $query = format_query("SELECT (SELECT COUNT(*) FROM :Event WHERE Level = %d) AS Events,
                           (SELECT COUNT(*) FROM :Tournament WHERE Level = %d) AS Tournaments", $id, $id);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $temp = mysql_fetch_assoc($result);
      $retValue = ($temp['Events'] + $temp['Tournaments']) > 0;
   }
   mysql_free_result($result);

   return $retValue;
}


function EditTournament($id, $name, $method, $level, $available, $year, $description)
{
   $query = format_query("UPDATE :Tournament SET Name = '%s', ScoreCalculationMethod = '%s', Level = %d, Available = %d, Year = %d,
                       Description = '%s'
                       WHERE id = %d",
                           escape_string($name), escape_string($method), (int) $level, $available ? 1 : 0, (int) $year,
                           escape_string($description),(int) $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function CreateTournament($name, $method, $level, $available, $year, $description)
{
   $query = format_query("INSERT INTO :Tournament(Name, ScoreCalculationMethod, Level, Available, Year, Description)
                        VALUES('%s', '%s', %d, %d, %d, '%s')",
                           escape_string($name), escape_string($method), (int) $level, $available ? 1 : 0, (int) $year,
                           escape_string($description));
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function DeleteTournament($id)
{
   $query = format_query("DELETE FROM :Tournament WHERE id = ". (int) $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


// Returns true if the provided tournament is being used in any event, false otherwise
function TournamentBeingUsed($id)
{
   $retValue = true;
   $query = format_query("SELECT COUNT(*) AS n FROM :Event WHERE Tournament = ". (int) $id);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $temp = mysql_fetch_assoc($result);
      $retValue = $temp['n'] > 0;
   }
   mysql_free_result($result);

   return $retValue;
}


function GetTournamentDetails($id)
{
    require_once 'core/tournament.php';

    $id = (int) $id;
    $retValue = array();

    $query = format_query("SELECT id, Level, Name, ScoreCalculationMethod, Year, Available, Description FROM :Tournament WHERE id = $id");
    $result = execute_query($query);

    if (mysql_num_rows($result) == 1) {
      while ($row = mysql_fetch_assoc($result))
         $retValue = new Tournament($row['id'], $row['Level'], $row['Name'], $row['Year'], $row['ScoreCalculationMethod'], $row['Available'], $row['Description']);
    }
    mysql_free_result($result);

    return $retValue;
}


function GetTournamentYears()
{
   $retValue = array();
   $query = format_query("SELECT DISTINCT Year FROM :Tournament ORDER BY Year");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row['Year'];
   }
   mysql_free_result($result);

   return $retValue;
}


function GetTournamentLeader($tournamentId)
{
   $tournamentId = (int) $tournamentId;
   $retValue = array();
   $query = format_query("SELECT :User.id FROM
                           :TournamentStanding
                           INNER JOIN :Player ON :TournamentStanding.Player = :Player.player_id
                           INNER JOIN :User ON :Player.player_id = :User.Player
                           WHERE :TournamentStanding.Tournament = $tournamentId
                           ORDER BY Standing
                           LIMIT 1");
   $result = execute_query($query);

   if (mysql_num_rows($result) == 1) {
      $row = mysql_fetch_assoc($result);
      $retValue = GetUserDetails($row['id']);
   }
   mysql_free_result($result);

   return $retValue;
}


function GetEventsByYear($year)
{
   $year = (int) $year;
   $start = mktime(0,0,0,1,1,$year);
   $end = mktime(0,0,0,12,31,$year);

   return GetEventsByDate($start, $end) ;
}


function GetEventYears()
{
   $retValue = array();
   $query = format_query("SELECT DISTINCT(YEAR(Date)) AS year FROM :Event ORDER BY YEAR(Date) ASC");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row['year'];
   }
   mysql_free_result($result);

   return $retValue;
}


function GetUserEvents($ignored, $eventType = 'all')
{
   $conditions = '';
   if ($eventType == 'participant' || $eventType == 'all')
      $conditions = ':Participation.Player IS NOT NULL';

   if ($eventType == 'manager' || $eventType == 'all') {
      if ($conditions)
         $conditions .= " OR ";
      $conditions = ':EventManagement.Role IS NOT NULL';
   }

   return data_GetEvents($conditions);
}


function GetFeePayments($relevantOnly = true, $search = '', $sortedBy = '', $forcePlayer = null)
{
   require_once 'core/player.php';

   if ($forcePlayer)
      $search = format_query( ":Player.player_id = %d", (int) $forcePlayer);
   else
      $search = data_ProduceSearchConditions($search, array('FirstName', 'LastName', 'pdga', 'Username'));

   $sortOrder = data_CreateSortOrder($sortedBy, array('name' => array('LastName', 'FirstName'), 'LastName' => true, 'FirstName' => true, 'pdga', 'gender' => 'sex', 'Username'));
   $year = date("Y");

   $query = "SELECT :User.id AS UserId, Username, Role, FirstName, LastName, Email,
                                :Player.player_id AS PlayerId, pdga PDGANumber, sex Sex, YEAR(birthdate) YearOfBirth,
                                :MembershipPayment.Year AS MSPYear,
                                :LicensePayment.Year AS LPYear
                  FROM :User
                  INNER JOIN :Player ON :Player.player_id = :User.Player
                  LEFT JOIN :MembershipPayment ON :MembershipPayment.Player = :Player.player_id ".($relevantOnly ? "AND :MembershipPayment.Year >= $year " : "")."
                  LEFT JOIN :LicensePayment ON :LicensePayment.Player = :Player.player_id".($relevantOnly ? " AND :LicensePayment.Year >= $year" : "").
                   " WHERE %s
                   ORDER BY %s, UserId, :MembershipPayment.Year, :LicensePayment.Year";

   $query = format_query($query, $search, $sortOrder);
   $result = execute_query($query);

   $userid = -1;
   $pdata = array();
   $retValue = array();

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         if ($userid != $row['UserId']) {
            if (!empty($pdata)) {
               if (!isset($pdata['licensefees'][$year ]))
                  $pdata['licensefees'][$year] = false;
               if (!isset($pdata['licensefees'][$year + 1]))
                  $pdata['licensefees'][$year + 1] = false;

               if (!isset($pdata['membershipfees'][$year ]))
                  $pdata['membershipfees'][$year] = false;
               if (!isset($pdata['membershipfees'][$year + 1]))
                  $pdata['membershipfees'][$year + 1] = false;

               ksort($pdata['membershipfees']);
               ksort($pdata['licensefees']);

               $retValue[] = $pdata;
            }

            $userid = $row['UserId'];
            $pdata = array();

            $pdata['user'] = new User($row['UserId'], $row['Username'], $row['Role'], $row['FirstName'], $row['LastName'], $row['Email'], $row['PlayerId']);
            $pdata['player'] = new Player($row['PlayerId'], $row['PDGANumber'], $row['Sex'], $row['YearOfBirth'], $row['FirstName'], $row['LastName'], $row['Email']);
            $pdata['licensefees'] = array();
            $pdata['membershipfees'] = array();
         }

         if ($row['MSPYear'] != null)
            $pdata['membershipfees'][$row['MSPYear']] = true;

         if ($row['LPYear'] != null)
            $pdata['licensefees'][$row['LPYear']] = true;
      }

      if (!empty($pdata)) {
         if (!isset($pdata['licensefees'][$year ]))
            $pdata['licensefees'][$year] = false;
         if (!isset($pdata['licensefees'][$year + 1]))
            $pdata['licensefees'][$year + 1] = false;

         if (!isset($pdata['membershipfees'][$year ]))
            $pdata['membershipfees'][$year] = false;
         if (!isset($pdata['membershipfees'][$year + 1]))
            $pdata['membershipfees'][$year + 1] = false;

         ksort($pdata['membershipfees']);
         ksort($pdata['licensefees']);

         $retValue[] = $pdata;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


/* Return event's participant counts by class */
function GetEventParticipantCounts($eventId)
{
   $eventId = (int) $eventId;
   $query = format_query("SELECT count(*) as cnt, Classification
      FROM :Participation
      WHERE Event = $eventId
      GROUP BY Classification");
   $result = execute_query($query);

   $ret = array();
   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $ret[$row['Classification']] = $row['cnt'];
   }

   return $ret;
}


function GetEventParticipants($eventId, $sortedBy, $search)
{
   $retValue = array();
   $eventId = (int) $eventId;
   $sortOrder = data_CreateSortOrder($sortedBy, array('name' => array('LastName', 'FirstName'), 'class' => 'ClassName', 'LastName' => true, 'FirstName' => true, 'birthyear' => 'YEAR(birthdate)', 'pdga', 'gender' => 'sex', 'Username'));

   if (is_a($sortOrder, 'Error'))
      return $sortOrder;
   if ($sortOrder == 1)
      $sortOrder = " LastName, FirstName";

   $query = "SELECT :User.id AS UserId, Username, Role, UserFirstName, UserLastName, UserEmail, :Player.firstname pFN, :Player.lastname pLN,
                                :Player.email pEM,
                               :Player.player_id AS PlayerId, pdga PDGANumber, Sex, YEAR(birthdate) YearOfBirth, :Classification.Name AS ClassName,
                               :Participation.id AS ParticipationID, UNIX_TIMESTAMP(EventFeePaid) EventFeePaid,
                               UNIX_TIMESTAMP(SignupTimestamp) SignupTimestamp, :Classification.id AS ClassId
                  FROM :User
                  INNER JOIN :Player ON :Player.player_id = :User.Player
                  INNER JOIN :Participation ON :Participation.Player = :Player.player_id AND :Participation.Event = ".$eventId ."
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  WHERE %s
                  ORDER BY $sortOrder";

   $query = format_query($query, data_ProduceSearchConditions($search, array('FirstName', 'LastName', 'pdga', 'Username', 'birthdate')));
   $result = execute_query($query);

   require_once 'core/player.php';
   require_once 'core/user.php';

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $pdata = array();

         $firstname = data_GetOne( $row['UserFirstName'], $row['pFN']);
         $lastname = data_GetOne( $row['UserLastName'], $row['pLN']);
         $email = data_GetOne($row['UserEmail'], $row['pEM']);

         $pdata['user'] = new User($row['UserId'], $row['Username'], $row['Role'], $firstname, $lastname, $email, $row['PlayerId']);
         $pdata['player'] = new Player($row['PlayerId'], $row['PDGANumber'], $row['Sex'], $row['YearOfBirth'], $firstname, $lastname, $email);

         $pdata['eventFeePaid'] = $row['EventFeePaid'];
         $pdata['participationId'] = $row['ParticipationID'];
         $pdata['signupTimestamp'] = $row['SignupTimestamp'];
         $pdata['className'] = $row['ClassName'];
         $pdata['classId'] = $row['ClassId'];
         $retValue[] = $pdata;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


/* Return event's queue counts by class */
function GetEventQueueCounts($eventId)
{
   $eventId = (int) $eventId;
   $query = format_query("SELECT count(*) as cnt, Classification
      FROM :EventQueue
      WHERE Event = $eventId
      GROUP BY Classification");
   $result = execute_query($query);

   $ret = array();
   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $ret[$row['Classification']] = $row['cnt'];
   }
   mysql_free_result($result);

   return $ret;
}


// This is more or less copypaste from ^^
// FIXME: Redo to a simpler form sometime
function GetEventQueue($eventId, $sortedBy, $search)
{
   $retValue = array();
   $eventId = (int) $eventId;

   $query = "SELECT :User.id AS UserId, Username, Role, UserFirstName, UserLastName, UserEmail,
               :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM, :Player.player_id AS PlayerId,
               pdga PDGANumber, Sex, YEAR(birthdate) YearOfBirth, :Classification.Name AS ClassName,
               :EventQueue.id AS QueueId,
               UNIX_TIMESTAMP(SignupTimestamp) SignupTimestamp, :Classification.id AS ClassId
                  FROM :User
                  INNER JOIN :Player ON :Player.player_id = :User.Player
                  INNER JOIN :EventQueue ON :EventQueue.Player = :Player.player_id AND :EventQueue.Event = ".$eventId ."
                  INNER JOIN :Classification ON :EventQueue.Classification = :Classification.id
                  WHERE %s
                  ORDER BY SignupTimestamp ASC, :EventQueue.id ASC";

   $query = format_query($query, data_ProduceSearchConditions($search, array('FirstName', 'LastName', 'pdga', 'Username', 'birthdate')));
   $result = execute_query($query);

   require_once 'core/player.php';
   require_once 'core/user.php';

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $pdata = array();

         $firstname = data_GetOne( $row['UserFirstName'], $row['pFN']);
         $lastname = data_GetOne( $row['UserLastName'], $row['pLN']);
         $email = data_GetOne($row['UserEmail'], $row['pEM']);

         $pdata['user'] = new User($row['UserId'], $row['Username'], $row['Role'], $firstname, $lastname, $email, $row['PlayerId']);
         $pdata['player'] = new Player($row['PlayerId'], $row['PDGANumber'], $row['Sex'], $row['YearOfBirth'], $firstname, $lastname, $email);
         $pdata['queueId'] = $row['QueueId'];
         $pdata['signupTimestamp'] = $row['SignupTimestamp'];
         $pdata['className'] = $row['ClassName'];
         $pdata['classId'] = $row['ClassId'];
         $retValue[] = $pdata;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetParticipantsForRound($previousRoundId)
{
   $retValue = array();
   $rrid = (int) $previousRoundId;
   $query = "SELECT :User.id AS UserId, Username, :Player.firstname FirstName, :Player.lastname LastName, Role, :Player.email Email, Sex, YEAR(birthdate) YearOfBirth,
                               :Player.player_id AS PlayerId, pdga PDGANumber, Classification,
                               :Participation.id AS ParticipationID,
                               :RoundResult.Result, :Participation.DidNotFinish
                  FROM `:Round`
                  INNER JOIN :RoundResult ON :RoundResult.`Round` = `:Round`.id
                  INNER JOIN :Participation ON (:Participation.Player = :RoundResult.Player AND :Participation.Event = `:Round`.Event)
                  INNER JOIN :Player ON :RoundResult.Player = :Player.player_id
                  INNER JOIN :User ON :Player.player_id = :User.Player
                  WHERE :RoundResult.Round = $rrid
                  ORDER BY :Participation.Standing";

   $query = format_query($query);
   $result = execute_query($query);

   require_once 'core/player.php';
   require_once 'core/user.php';

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $pdata = array();

         $pdata['user'] = new User($row['UserId'], $row['Username'], $row['Role'], $row['FirstName'], $row['LastName'], $row['Email'], $row['PlayerId']);
         $pdata['player'] = new Player($row['PlayerId'], $row['PDGANumber'], $row['Sex'], $row['YearOfBirth'], $row['FirstName'], $row['LastName'], $row['Email']);
         $pdata['participationId'] = $row['ParticipationID'];
         $pdata['classification'] = $row['Classification'];
         $pdata['result'] = $row['Result'];
         $pdata['didNotFinish']=  $row['DidNotFinish'];

         $retValue[] = $pdata;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function SaveTextContent($page)
{
   if (!is_a($page, 'TextContent'))
      return Error::notFound('textcontent');

   if (!$page->id) {
      $query = format_query("INSERT INTO :TextContent (Event, Title, Content, Date, Type, `Order`)
                       VALUES (%s, '%s', '%s', FROM_UNIXTIME(%d), '%s', %d)",
                       esc_or_null($page->event, "int"),
                       escape_string($page->title),
                       escape_string($page->content),
                       time(),
                       escape_string($page->type),
                       0);
   }
   else {
      $query = format_query("UPDATE :TextContent
                           SET
                              Title = '%s',
                              Content = '%s',
                              Date = FROM_UNIXTIME(%d),
                              `Type` = '%s'
                              WHERE id = %d",

                              mysql_real_escape_string($page->title),
                              mysql_real_escape_string($page->content),
                              time(),
                              $page->type,
                              (int) $page->id);
   }
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function GetRoundHoles($roundId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $roundId = (int) $roundId;
   $query = format_query("SELECT :Hole.id, :Hole.Course, HoleNumber, HoleText, Par, Length, :Round.id Round
                         FROM :Hole
                         INNER JOIN :Course ON (:Course.id = :Hole.Course)
                         INNER JOIN :Round ON (:Round.Course = :Course.id)
                         WHERE :Round.id = $roundId
                         ORDER BY HoleNumber");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      while ($row = mysql_fetch_assoc($result)) {
         $hole =  new Hole($row);
         $retValue[] = $hole;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetCourseHoles($courseId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $query= format_query("SELECT id, Course, HoleNumber, HoleText, Par, Length FROM :Hole
                         WHERE Course = %d
                         ORDER BY HoleNumber", $courseId);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      while ($row = mysql_fetch_assoc($result)) {
         $hole =  new Hole($row);
         $retValue[] = $hole;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function CourseUsed($courseId)
{
   $query = format_query("SELECT id FROM `:Round` WHERE `:Round`.Course = %d LIMIT 1", $courseId);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_num_rows($result) == 1;
}


function GetEventHoles($eventId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $eventId = (int) $eventId;
   $query = format_query("SELECT :Hole.id, :Hole.Course, HoleNumber, HoleText, Par, Length, :Round.id AS Round FROM :Hole
                         INNER JOIN :Course ON (:Course.id = :Hole.Course)
                         INNER JOIN :Round ON (:Round.Course = :Course.id)
                         INNER JOIN :Event ON :Round.Event = :Event.id
                         WHERE :Event.id = $eventId
                         ORDER BY :Round.StartTime, HoleNumber");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      while ($row = mysql_fetch_assoc($result)) {
         $hole =  new Hole($row);
         $retValue[] = $hole;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetHoleDetails($holeid)
{
   require_once 'core/hole.php';

   $retValue = null;
   $holeid = (int) $holeid;
   $query = format_query("SELECT :Hole.id, :Hole.Course, HoleNumber, HoleText, Par, Length,
                         :Course.id CourseId, :Round.id RoundId FROM :Hole
                         LEFT JOIN :Course ON (:Course.id = :Hole.Course)
                         LEFT JOIN :Round ON (:Round.Course = :Course.id)
                         WHERE :Hole.id = $holeid
                         ORDER BY HoleNumber");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      $row = mysql_fetch_assoc($result);
      $retValue =  new Hole($row);
   }
   mysql_free_result($result);

   return $retValue;
}


function GetRoundResults($roundId, $sortedBy)
{
   require_once 'core/hole.php';

   $groupByClass = false;
   if ($sortedBy == 'resultsByClass')
      $groupByClass = true;

   $retValue = array();
   $roundId = (int) $roundId;

   $query = "SELECT :Player.player_id as PlayerId, :Player.firstname FirstName, :Player.lastname LastName, :Player.pdga PDGANumber,
                 :RoundResult.Result AS Total, :RoundResult.Penalty, :RoundResult.SuddenDeath,
                 :StartingOrder.GroupNumber, (:HoleResult.Result - :Hole.Par) AS Plusminus, Completed,
                 :HoleResult.Result AS HoleResult, :Hole.id AS HoleId, :Hole.HoleNumber, :RoundResult.PlusMinus RoundPlusMinus,
                 :Classification.Name ClassName, CumulativePlusminus, CumulativeTotal, :RoundResult.DidNotFinish,
                 :Classification.id Classification
                         FROM :Round
                         LEFT JOIN :Section ON :Round.id = :Section.Round
                         LEFT JOIN :StartingOrder ON (:StartingOrder.Section = :Section.id )
                         LEFT JOIN :RoundResult ON (:RoundResult.Round = :Round.id AND :RoundResult.Player = :StartingOrder.Player)
                         LEFT JOIN :HoleResult ON (:HoleResult.RoundResult = :RoundResult.id AND :HoleResult.Player = :StartingOrder.Player)
                         LEFT JOIN :Player ON :StartingOrder.Player = :Player.player_id
                         LEFT JOIN :User ON :Player.player_id = :User.Player
                         LEFT JOIN :Participation ON (:Participation.Player = :Player.player_id AND
                                                     :Participation.Event = :Round.Event)
                         LEFT JOIN :Classification ON :Classification.id = :Participation.Classification
                         LEFT JOIN :Hole ON :HoleResult.Hole = :Hole.id
                         WHERE :Round.id = $roundId AND :Section.Present";

   switch ($sortedBy) {
     case 'group':
         $query .= " ORDER BY :StartingOrder.GroupNumber, :StartingOrder.id";
         break;

     case 'results':
     case 'resultsByClass':
         $query .= " ORDER BY (:RoundResult.DidNotFinish IS NULL OR :RoundResult.DidNotFinish = 0) DESC,  :Hole.id IS NULL, :RoundResult.CumulativePlusminus, :Player.player_id";
         break;

     default:
         return Error::InternalError();
   }

   $query = format_query($query);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      $lastrow = null;

      while ($row = mysql_fetch_assoc($result)) {
         if (!$row['PlayerId'])
            continue;

         if (@$lastrow['PlayerId'] != $row['PlayerId']) {
            if ($lastrow) {
               if ($groupByClass) {
                  $class = $lastrow['ClassName'];
                  if (!isset($retValue[$class]))
                     $retValue[$class] = array();
                  $retValue[$class][] = $lastrow;
               }
               else {
                  $retValue[] = $lastrow;
               }
            }
            $lastrow = $row;
            $lastrow['Results'] = array();
            $lastrow['TotalPlusMinus'] = $lastrow['Penalty'];
         }

         $lastrow['Results'][$row['HoleNumber']] = array(
            'Hole' => $row['HoleNumber'],
            'HoleId' => $row['HoleId'],
            'Result' => $row['HoleResult']);

         $lastrow['TotalPlusMinus'] += $row['Plusminus'];
      }

      if ($lastrow) {
         if ($groupByClass) {
            $class = $lastrow['ClassName'];
            if (!isset($retValue[$class]))
               $retValue[$class] = array();
            $retValue[$class][] = $lastrow;
         }
         else {
             $retValue[] = $lastrow;
         }
      }
   }
   mysql_free_result($result);

   if ($sortedBy == 'resultsByClass')
      $retValue = data_FinalizeResultSort($roundId, $retValue);

   return $retValue;
}


function GetEventResults($eventId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $eventId = (int) $eventId;

   $query = "SELECT :Participation.*, player_id as PlayerId, :Player.firstname FirstName, :Player.lastname LastName, :Player.pdga PDGANumber,
                 :RoundResult.Result AS Total, :RoundResult.Penalty, :RoundResult.SuddenDeath,
                 :StartingOrder.GroupNumber, (:HoleResult.Result - :Hole.Par) AS Plusminus,
                 :HoleResult.Result AS HoleResult, :Hole.id AS HoleId, :Hole.HoleNumber,
                 :Classification.Name ClassName,
                 TournamentPoints, :Round.id RoundId,
                 :Participation.Standing
                         FROM :Round
                         INNER JOIN :Event ON :Round.Event = :Event.id
                         INNER JOIN :Section ON :Section.Round = :Round.id
                         INNER JOIN :StartingOrder ON (:StartingOrder.Section = :Section.id )
                         LEFT JOIN :RoundResult ON (:RoundResult.Round = :Round.id AND :RoundResult.Player = :StartingOrder.Player)
                         LEFT JOIN :HoleResult ON (:HoleResult.RoundResult = :RoundResult.id AND :HoleResult.Player = :StartingOrder.Player)
                         LEFT JOIN :Player ON :StartingOrder.Player = :Player.player_id
                         LEFT JOIN :Participation ON (:Participation.Event = $eventId AND :Participation.Player = :Player.player_id)
                         LEFT JOIN :Classification ON :Participation.Classification = :Classification.id
                         LEFT JOIN :User ON :Player.player_id = :User.Player
                         LEFT JOIN :Hole ON :HoleResult.Hole = :Hole.id
                         WHERE :Event.id = $eventId AND :Section.Present
                         ORDER BY :Participation.Standing, player_id, :Round.StartTime, :Hole.HoleNumber";

   $query = format_query($query);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $penalties = array();
   if (mysql_num_rows($result) > 0) {
      $index = 1;
      $lastrow = null;

      while ($row = mysql_fetch_assoc($result)) {
         if (!$lastrow || @$lastrow['PlayerId'] != $row['PlayerId']) {
            if ($lastrow)
               $retValue[] = $lastrow;
            $lastrow = $row;
            $lastrow['Results'] = array();
            $lastrow['TotalPlusMinus'] = $lastrow['Penalty'];
            $penalties[$row['RoundId']] = true;
         }

         if (!@$penalties[$row['RoundId']]) {
            $penalties[$row['RoundId']] = true;
            $lastrow['Penalty'] += $row['Penalty'];
         }

         if ($row['HoleResult']) {
            $lastrow['Results'][$row['RoundId'] . '_' . $row['HoleNumber']] = array(
               'Hole' => $row['HoleNumber'],
               'HoleId' => $row['HoleId'],
               'Result' => $row['HoleResult']);
            $lastrow['TotalPlusMinus'] += $row['Plusminus'];
         }
      }

      if ($lastrow)
         $retValue[] = $lastrow;
   }
   mysql_free_result($result);

   return $retValue;
}


function GetEventResultsWithoutHoles($eventId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $eventId = (int) $eventId;

   $query = "SELECT :Participation.*, player_id as PlayerId, :Player.firstname FirstName, :Player.lastname LastName, :Player.pdga PDGANumber,
                 :RoundResult.Result AS Total, :RoundResult.Penalty, :RoundResult.SuddenDeath,
                 :StartingOrder.GroupNumber, CumulativePlusminus, Completed  ,
                 :Classification.Name ClassName, PlusMinus, :StartingOrder.id StartId,
                 TournamentPoints, :Round.id RoundId,
                 :Participation.Standing
                         FROM :Round
                         INNER JOIN :Event ON :Round.Event = :Event.id
                         INNER JOIN :Section ON :Section.Round = :Round.id
                         INNER JOIN :StartingOrder ON (:StartingOrder.Section = :Section.id )
                         LEFT JOIN :RoundResult ON (:RoundResult.Round = :Round.id AND :RoundResult.Player = :StartingOrder.Player)
                         LEFT JOIN :Player ON :StartingOrder.Player = :Player.player_id
                         LEFT JOIN :Participation ON (:Participation.Event = $eventId AND :Participation.Player = :Player.player_id)
                         LEFT JOIN :Classification ON :Participation.Classification = :Classification.id
                         LEFT JOIN :User ON :Player.player_id = :User.Player
                         WHERE :Event.id = $eventId AND :Section.Present
                         ORDER BY :Participation.Standing, player_id, :Round.StartTime";
   $query = format_query($query);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $penalties = array();
   if (mysql_num_rows($result) > 0) {
      $index = 1;
      $lastrow = null;

      while ($row = mysql_fetch_assoc($result)) {
         if (!$lastrow || @$lastrow['PlayerId'] != $row['PlayerId']) {
            if ($lastrow)
               $retValue[] = $lastrow;

            $lastrow = $row;
            $lastrow['Results'] = array();
            $lastrow['TotalCompleted'] = 0;
            $lastrow['TotalPlusminus'] = 0;
         }

         $lastrow['TotalCompleted'] += $row['Completed'];
         $lastrow['TotalPlusminus'] += $row['PlusMinus'];
         $lastrow['Results'][$row['RoundId']] = $row;
      }

      if ($lastrow)
         $retValue[] = $lastrow;
   }
   mysql_free_result($result);

   usort($retValue, 'data_sort_leaderboard');

   return $retValue;
}


function GetTournamentResults($tournamentId)
{
   require_once 'core/hole.php';

   $retValue = array();
   $tournamentId = (int) $tournamentId;

   $query = "SELECT :Player.player_id as PlayerId, :Player.firstname FirstName , :Player.lastname LastName, :Player.pdga PDGANumber, :User.Username,
                 :TournamentStanding.OverallScore, :TournamentStanding.Standing,
                 :Event.id EventId, :Classification.Name ClassName, TieBreaker,
                 :Participation.Standing AS EventStanding, :Participation.TournamentPoints AS EventScore
               FROM
                  :Tournament
                  INNER JOIN :Event ON :Event.Tournament = :Tournament.id
                  INNER JOIN :Participation ON :Participation.Event = :Event.id
                  INNER JOIN :Player ON :Participation.Player = :Player.player_id
                  INNER JOIN :User ON :User.Player = :Player.player_id
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  LEFT JOIN :TournamentStanding ON (:TournamentStanding.Tournament = :Tournament.id AND :TournamentStanding.Player = :Player.player_id)
                  WHERE :Tournament.id = $tournamentId AND :Event.ResultsLocked IS NOT NULL
                  ORDER BY
                     :TournamentStanding.Standing,
                     :Player.lastname,
                     :Player.firstname";
   $query = format_query($query);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) > 0) {
      $index = 1;
      $lastrow = null;

      while ($row = mysql_fetch_assoc($result)) {
         if (@$lastrow['PlayerId'] != $row['PlayerId']) {
            if ($lastrow)
               $retValue[] = $lastrow;
            $lastrow = $row;
            $lastrow['Results'] = array();
         }

         $lastrow['Results'][$row['EventId']] = array(
            'Event' => $row['EventId'],
            'Standing' => $row['EventStanding'],
            'Score' => $row['EventScore']);
      }

      if ($lastrow)
         $retValue[] = $lastrow;
   }
   mysql_free_result($result);

   return $retValue;
}


function GetSignupsForClass($event, $class)
{
   $classId = (int) $class;
   $eventId = (int) $event;

   $retValue = array();
   $query = format_query("SELECT :Player.id PlayerId, :User.FirstName, :User.LastName, :Player.PDGANumber,
                    :Participation.id ParticipationId
                 FROM :User
                 INNER JOIN :Player ON User.id = :Player.User
                 INNER JOIN :Participation ON :Participation.Player = :Player.id
                 WHERE :Participation.Classification = $classId
                   AND :Participation.Event = $eventId");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row;
   }
   mysql_free_result($result);

   return $retValue;
}


function GetQueueForClass($event, $class)
{
   $classId = (int) $class;
   $eventId = (int) $event;

   $retValue = array();
   $query = format_query("SELECT :Player.id PlayerId, :User.FirstName, :User.LastName, :Player.PDGANumber,
                    :EventQueue.id ParticipationId
                 FROM :User
                 INNER JOIN :Player ON User.id = :Player.User
                 INNER JOIN :Participation ON :EventQueue.Player = :Player.id
                 WHERE :EventQueue.Classification = $classId
                   AND :EventQueue.Event = $eventId");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result))
         $retValue[] = $row;
   }
   mysql_free_result($result);

   return $retValue;
}


function GetSectionMembers($sectionId)
{
   $sectionId = (int) $sectionId;

   $retValue = array();
   $query = format_query("SELECT :Player.player_id PlayerId, :User.UserFirstName, :User.UserLastName, :Player.pdga PDGANumber,
                 :Player.firstname pFN, :Player.lastname pLN, :Player.email pEM, :Classification.Name Classification,
                    SM.id as MembershipId, :Participation.OverallResult
                 FROM :User
                 INNER JOIN :Player ON :User.Player = :Player.player_id
                 INNER JOIN :Participation ON :Player.player_id = :Participation.Player
                 INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                 INNER JOIN :SectionMembership SM ON SM.Participation = :Participation.id
                 WHERE SM.Section = $sectionId
                   ORDER BY SM.id");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $row['FirstName'] = data_GetOne($row['UserFirstName'], $row['pFN']);
         $row['LastName'] = data_GetOne($row['UserLastName'], $row['pLN']);
         $retValue[] = $row;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetAllAds($eventid)
{
   $retValue = array();

   $eventCond = $eventid ? " = " . (int) $eventid : " IS NULL";
   $eventid =  esc_or_null( $eventid, 'int');
   $query = format_query("SELECT id, Event, URL, ImageURL, LongData, ImageReference, Type, ContentId  FROM :AdBanner WHERE Event $eventCond");
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      while ($row = mysql_fetch_assoc($result)) {
         $temp = new Ad($row);
         $retValue[] = $temp;
      }
   }
   mysql_free_result($result);

   return $retValue;
}


function GetAd($eventid, $contentId)
{
   require_once 'core/ads.php';
   $retValue = null;

   $eventCond = $eventid ? " = " . (int) $eventid : " IS NULL";
   $contentId = mysql_real_escape_string($contentId);
   $eventid =  esc_or_null( $eventid, 'int');
   $query = format_query("SELECT id, Event, URL, ImageURL, LongData, ImageReference, Type, ContentId
                     FROM :AdBanner WHERE Event $eventCond AND ContentId = '%s'", $contentId);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   if (mysql_num_rows($result) > 0) {
      $row = mysql_fetch_assoc($result);
      $retValue = new Ad($row);
   }
   mysql_free_result($result);

   return $retValue;
}


function InitializeAd($eventid, $contentId)
{
   require_once 'core/ads.php';
   $retValue = array();

   $contentId = mysql_real_escape_string($contentId);
   $query = format_query( "INSERT INTO :AdBanner (Event, URL, ImageURL, LongData, ImageReference, Type, ContentId)
                    VALUES (%s, NULL, NULL, NULL, NULL, '%s', '%s')",
                    esc_or_null($eventid, 'int'), ($eventid ? AD_EVENT_DEFAULT : AD_DEFAULT), mysql_real_escape_string($contentId));
   $result = execute_query($query);

   if (!$result)
     return Error::Query($query, 'InitializeAd');

   return GetAd($eventid, $contentId);
}


function data_ProduceSearchConditions($queryString, $fields)
{
   if (trim($queryString) == "")
      return "1";

   $words = explode(' ', $queryString);
   $words = array_filter($words, 'data_RemoveEmptyStrings');
   $words = array_map('mysql_real_escape_string', $words);
   $wordSpecificBits = array();

   if (!count($words))
      return "1";

   foreach ($words as $word) {
      $fieldSpecificBits = array();
      foreach ($fields as $field) {
         $field = str_replace('.', '`.`', $field);
         $fieldSpecificBits[] = "(`$field` LIKE '%$word%')";
      }
      $wordSpecificBits[] = '('. implode(' OR ', $fieldSpecificBits) . ')';
   }

   return '(' . implode(' AND ', $wordSpecificBits) . ')';
}


function data_RemoveEmptyStrings($item)
{
   return $item !== '';
}


function GetResultUpdatesSince($eventId, $roundId, $time)
{
   if ((int) $time < 10)
      $time = 10;

   if ($roundId) {
      $query = format_query("SELECT :HoleResult.Player, :HoleResult.Hole, :HoleResult.Result,
                          :RoundResult.`Round`, :Hole.HoleNumber
                       FROM :HoleResult
                       INNER JOIN :RoundResult ON :HoleResult.RoundResult = :RoundResult.id
                       INNER JOIN :Hole ON :Hole.id = :HoleResult.Hole
                       WHERE :RoundResult.`Round` = %d
                       AND :HoleResult.LastUpdated > FROM_UNIXTIME(%d)
                       ", $roundId, $time - 2);
   }
   else {
      $query = format_query("SELECT :HoleResult.Player, :HoleResult.Hole, :HoleResult.Result,
                          HoleNumber,
                          :RoundResult.`Round`
                       FROM :HoleResult
                       INNER JOIN :RoundResult ON :HoleResult.RoundResult = :RoundResult.id
                       INNER JOIN `:Round` ON `:Round`.id = :RoundResult.`Round`
                       INNER JOIN :Hole ON :Hole.id = :HoleResult.Hole
                       WHERE `:Round`.Event = %d
                       AND :HoleResult.LastUpdated > FROM_UNIXTIME(%d)
                       ", $eventId, $time - 2);
   }

   $out = array();
   $result = execute_query($query);

   while (($row = mysql_fetch_assoc($result)) !== false) {
      $out[] = array(
         'PlayerId' => $row['Player'],
         'HoleId' => $row['Hole'],
         'HoleNum' => $row['HoleNumber'],
         'Special' => null,
         'Value' => $row['Result'],
         'RoundId' => $row['Round']);
   }
   mysql_free_result($result);

   $query = format_query("SELECT Result, Player, SuddenDeath, Penalty, Round
                    FROM :RoundResult
                    WHERE :RoundResult.`Round` = %d
                    AND LastUpdated > FROM_UNIXTIME(%d)
                    ", $roundId, $time);
   $result = execute_query($query);

   while (($row = mysql_fetch_assoc($result)) !== false) {
      $out[] = array(
         'PlayerId' => $row['Player'],
         'HoleId' => null,
         'HoleNum' => 0,
         'Special' => 'Sudden Death',
         'Value' => $row['SuddenDeath'],
         'RoundId' => $row['Round']);
      $out[] = array(
         'PlayerId' => $row['Player'],
         'HoleId' => null,
         'HoleNum' => 0,
         'Special' => 'Penalty',
         'Value' => $row['Penalty'],
         'RoundId' => $row['Round']);
   }
   mysql_free_result($result);

   return $out;
}


function SaveResult($roundid, $playerid, $holeid, $special, $result)
{
   $rrid = GetRoundResult($roundid, $playerid);
   if (is_a($rrid, 'Error'))
      return $rrid;

   if ($holeid === null)
      return data_UpdateRoundResult($rrid, $special, $result);

   return data_UpdateHoleResult($rrid, $playerid, $holeid, $result);
}


function data_UpdateHoleResult($rrid, $playerid, $holeid, $result)
{
   execute_query(format_query("LOCK TABLE :HoleResult WRITE"));

   $query = format_query("SELECT id FROM :HoleResult WHERE RoundResult = %d AND Player = %d AND Hole = %d",
      $rrid, $playerid, $holeid);
   $result = execute_query($query);

   if (mysql_num_rows($result) > 0) {
      $query = format_query("INSERT INTO :HoleResult (Hole, RoundResult, Player, Result, DidNotShow, LastUpdated) VALUES (%d, %d, %d, 0, 0, NOW())",
        $holeid, $rrid, $playerid);
      execute_query($query);
   }

   $dns = 0;
   if ($result == 99 || $result == 999) {
      $dns = 1;
      $result = 99;
   }

   $query = format_query("UPDATE :HoleResult SET Result = %d, DidNotShow = %d, LastUpdated = NOW() WHERE RoundResult = %d AND Hole = %d AND Player = %d",
                  $result, $dns, $rrid, $holeid, $playerid);
   $result = execute_query($query);

   execute_query(format_query("UNLOCK TABLES"));
   return data_UpdateRoundResult($rrid);
}


function data_UpdateRoundResult($rrid, $modifyField = null, $modValue = null)
{
   $query = format_query("SELECT `Round`, Penalty, SuddenDeath FROM :RoundResult WHERE id = %d", $rrid);
   $result = execute_query($query);
   if (!$result)
      return Error::Query($query);

   $details = mysql_fetch_assoc($result);
   $round = GetRoundDetails($details['Round']);
   $numHoles = $round->NumHoles();
   $query = format_query("SELECT Result, DidNotShow, :Hole.Par FROM :HoleResult
                        INNER JOIN :Hole ON :HoleResult.Hole = :Hole.id
                        WHERE RoundResult = %d", $rrid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $holes = $total = $plusminus = $dnf = 0;
   while (($row = mysql_fetch_assoc($result)) !== false) {
      if ($row['DidNotShow']) {
         $dnf = true;
         break;
      }
      else {
         if ($row['Result']) {
            $total += $row['Result'];
            $ppm = $plusminus;
            $plusminus += $row['Result'] - $row['Par'];
            $holes++;
         }
      }
   }
   $total += $details['Penalty'];
   $plusminus += $details['Penalty'];
   $complete = ($total == 999) ? $numHoles : $holes;

   $penalty = $details['Penalty'];
   if ($modifyField == 'Penalty') {
     $total += $modValue - $details['Penalty'];
     $plusminus += $modValue - $details['Penalty'];
     $penalty = $modValue;
   }

   $suddendeath = $details['SuddenDeath'];
   if ($modifyField == 'Sudden Death')
      $suddendeath = $modValue;

   $query = format_query("UPDATE :RoundResult SET Result = %d, Penalty = %d, SuddenDeath = %d, Completed = %d,
                       DidNotFinish = %d, PlusMinus = %d, LastUpdated = NOW() WHERE id = %d",
                    $total, $penalty, $suddendeath, $complete, $dnf ? 1 : 0, $plusminus, $rrid);
   $result = execute_query($query);
   if (!$result)
      return Error::Query($query);

   UpdateCumulativeScores($rrid);
   UpdateEventResults($round->eventId);
}


function UpdateCumulativeScores($rrid)
{
   $query = format_query("
          SELECT :RoundResult.PlusMinus, :RoundResult.Result, :RoundResult.CumulativePlusminus,
                  :RoundResult.CumulativeTotal, :RoundResult.id,
                  :RoundResult.DidNotFinish
                  FROM :RoundResult
                  INNER JOIN `:Round` ON `:Round`.id = :RoundResult.`Round`
                  INNER JOIN `:Round` RX ON `:Round`.Event = RX.Event
                  INNER JOIN :RoundResult RRX ON RRX.`Round` = RX.id
                  WHERE RRX.id = %d AND RRX.Player = :RoundResult.Player
                  ORDER BY `:Round`.StartTime", $rrid);
   $result = execute_query($query);

   $total = 0;
   $pm = 0;
   while (($row = mysql_fetch_assoc($result)) !== false) {
      if (!$row['DidNotFinish']) {
         $total += $row['Result'];
         $pm += $row['PlusMinus'];
      }

      if ($row['CumulativePlusminus'] != $pm || $row['CumulativeTotal'] != $total) {
         $query = format_query("UPDATE :RoundResult SET CumulativeTotal = %d, CumulativePlusminus = %d WHERE id = %d",
                              $total, $pm, $row['id']);
         execute_query($query);
      }
   }
   mysql_free_result($result);
}


function GetRoundResult($roundid, $playerid)
{
   $id = 0;

   $result = execute_query(format_query("LOCK TABLE :RoundResult WRITE"));
   if ($result) {
      $query = format_query("SELECT id FROM :RoundResult WHERE `Round` = %d AND Player = %d", $roundid, $playerid);
      $result = execute_query($query);

      if ($result) {
         $id = 0;
         $rows = mysql_num_rows($result);
         /* FIXME: Need to pinpoint where exactly does this score mangling happen
          * that causes two roundresult rows for same player on same round be created.
          * Then fix it and then decommission this piece of code. */
         if ($rows > 1) {
            /* Cleanest thing we can do is to throw away all the invalid scores and return error.
             * This way TD knows to reload the scoring page and can alleviate the error by re-entering. */
            $query = format_query("DELETE FROM :RoundResult WHERE `Round` = %d AND Player = %d", $roundid, $playerid);
            $result = execute_query($query);
            // Fall thru the the end and return Error to get proper cleanup on the way
         }
         elseif (!mysql_num_rows($result)) {
            $query = format_query("INSERT INTO :RoundResult (`Round`, Player, Result, Penalty, SuddenDeath, Completed, LastUpdated)
                             VALUES (%d, %d, 0, 0, 0, 0, NOW())",
                             $roundid, $playerid);
            $result = execute_query($query);
            if ($result)
               $id = mysql_insert_id();
         }
         else {
            $row = mysql_fetch_assoc($result);
            $id = $row['id'];
         }
      }

      execute_query(format_query("UNLOCK TABLES"));
   }

   if ($id)
      return $id;

   return Error::Query($query);
}


function CreateSection($round, $baseClassId, $name)
{
   $round = (int) $round;
   $name = escape_string($name);

   $query = format_query("INSERT INTO :Section(Round, Classification, Name, Present)
      VALUES(%d, %s, '%s', 1)", $round, esc_or_null($baseClassId, 'int'), $name);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_insert_id();
}


function RenameSection($classId, $newName)
{
   $classId = (int) $classId;
   $newName = mysql_real_escape_string($newName);
   $query = format_query("UPDATE :Section SET Name = '%s' WHERE id = %d", $newName, $classId);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function AssignPlayersToSection($roundId, $sectionid, $playerIds)
{
   $each = array();
   foreach ($playerIds as $playerId)
      $each[] = sprintf("(%d, %d)", GetParticipationIdByRound($roundId, $playerId), $sectionid);

   $data = implode(", ", $each);
   $query = format_query("INSERT INTO :SectionMembership (Participation, Section) VALUES %s", $data);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function EditSection($roundId, $sectionId, $priority, $startTime)
{
   $roundId = (int) $roundId;
   $classId = (int) $classId;
   $priority = esc_or_null($priority, 'int');
   $startTime = esc_or_null($startTime, 'int');

   $query = format_query("UPDATE :ClassOnRound SET Priority = %s, StartTime = FROM_UNIXTIME(%s) WHERE Round = %d AND Classification = %d",
                            $priority, $startTime, $roundId, $classId);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


/**
 * Stores or removes the event fee payment of a single player
 */
function MarkEventFeePayment($eventid, $participationId, $payment)
{
   $query = format_query("UPDATE :Participation SET EventFeePaid = FROM_UNIXTIME(%s), Approved = 1 WHERE id = %d AND Event = %d",
                          ($payment ? time() : "NULL"), (int) $participationId, (int) $eventid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function SetRoundDetails($roundid, $date, $startType, $interval, $valid, $course)
{
   $query = format_query("UPDATE :Round SET StartTime = FROM_UNIXTIME(%d), StartType = '%s', `Interval` = %d, ValidResults = %d, Course = %s WHERE id = %d",
                    (int) $date,
                    mysql_real_escape_string($startType),
                    (int) $interval,
                    $valid ?  1:  0,
                    esc_or_null($course, 'int'),
                    $roundid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function SaveHole($hole)
{
   if ($hole->id) {
      $query = format_query("UPDATE :Hole SET Par = %d, Length = %d, HoleNumber = %d, HoleText = '%s' WHERE id = %d",
                       (int) $hole->par,
                       (int) $hole->length,
                       $hole->holeNumber,
                       $hole->holeText,
                       (int) $hole->id);
   }
   else {
      $query = format_query("INSERT INTO :Hole (Par, Length, Course, HoleNumber, HoleText) VALUES (%d, %d, %d, %d, '%s')",
                       (int) $hole->par,
                       (int) $hole->length,
                       (int) $hole->course,
                       $hole->holeNumber,
                       $hole->holeText);
   }
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function PlayerOnRound($roundid, $playerid)
{
   $query = format_query("SELECT :Participation.Player FROM :Participation
                     INNER JOIN :SectionMembership ON :SectionMembership.Participation = :Participation.id
                     INNER JOIN :Section ON :Section.id = :SectionMembership.Section
                     WHERE :Participation.Player = %d
                     AND   :Section.Round = %d
                     LIMIT 1",
                     $playerid,
                     $roundid);
   $result = execute_query($query);

   return mysql_num_rows($result) != 0;
}


function GetParticipationIdByRound($roundid, $playerid)
{
   $query = format_query("SELECT :Participation.id FROM :Participation
                     INNER JOIN :Event ON :Event.id = :Participation.Event
                     INNER JOIN :Round ON :Round.Event = :Event.id
                     WHERE :Participation.Player = %d
                     AND :Round.id = %d
                     ",
                     $playerid,
                     $roundid);
   $result = execute_query($query);

   if ($result)
      $row = mysql_fetch_assoc($result);
   mysql_free_result($result);

   if ($row === false)
      return null;

   return $row['id'];
}


function RemovePlayersFromRound($roundid, $playerids = null)
{
   if (!is_array($playerids))
      $playerids = array($playerids);

   $retValue = null;
   $playerids = array_filter($playerids, 'is_numeric');

   $query = format_query( "SELECT :SectionMembership.id FROM :SectionMembership
      INNER JOIN :Section ON :Section.id = :SectionMembership.Section
      INNER JOIN :Participation ON :Participation.id = :SectionMembership.Participation
      WHERE :Section.Round = %d AND :Participation.Player IN (%s)",
      $roundid, implode(", " ,$playerids));
   $result = execute_query($query);

   $ids = array();
   while (($row = mysql_fetch_assoc($result)) !== false) {
      $ids[] = $row['id'];
   }
   mysql_free_result($result);

   if (!count($ids))
      return;

   $query = format_query("DELETE FROM :SectionMembership WHERE id IN (%s)", implode(", ", $ids ));
   $result = execute_query($query);

    if (!$result)
      return Error::Query($query);

    return $retValue;
}


function ResetRound($roundid, $resetType = 'full')
{
   $sections = GetSections((int) $roundid);
   $sectIds = array();

   foreach ($sections as $section) {
      $sectIds[] = $section->id;
   }
   $idList = implode(', ', $sectIds);

   if ($resetType == 'groups' || $resetType == 'full')
      execute_query(format_query("DELETE FROM :StartingOrder WHERE Section IN ($idList)"));

   if ($resetType == 'full' || $resetType == 'players')
      execute_query(format_query("DELETE FROM :SectionMembership WHERE Section IN ($idList)"));

   if ($resetType == 'full')
      execute_query(format_query("DELETE FROM :Section WHERE id IN ($idList)"));
}


function RemoveEmptySections($round)
{
   $sections = GetSections((int) $round);
   foreach ($sections as $section) {
      $players = $section->GetPlayers();

      if (!count($players))
         execute_query(format_query("DELETE FROM :Section WHERE id = %d", $section->id));
   }
}


function AdjustSection($sectionid, $priority, $sectionTime, $present)
{
   $query = format_query("UPDATE :Section SET Priority = %d, StartTime = FROM_UNIXTIME(%s), Present = %d WHERE id = %d",
                     $priority,
                     esc_or_null($sectionTime, 'int'),
                     $present ? 1 : 0,
                     $sectionid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function GetGroups($sectid)
{
   $query = format_query("
         SELECT
            :Player.player_id PlayerId, :Player.pdga PDGANumber, :StartingOrder.Section,
            :StartingOrder.id, UNIX_TIMESTAMP(:StartingOrder.StartingTime) StartingTime, :StartingOrder.StartingHole, :StartingOrder.GroupNumber,
            :User.UserFirstName, :User.UserLastName, firstname pFN, lastname pLN, :Classification.Name Classification, :Participation.OverallResult
            FROM :StartingOrder
            INNER JOIN :Player ON :StartingOrder.Player = :Player.player_id
            INNER JOIN :User ON :Player.player_id = :User.Player
            INNER JOIN :Section ON :StartingOrder.Section = :Section.id
            INNER JOIN :Round ON :Round.id = :Section.Round
            INNER JOIN :Participation ON (:Participation.Player = :Player.player_id AND :Participation.Event = :Round.Event)
            INNER JOIN :Classification ON :Participation.Classification = :Classification.id
            WHERE :StartingOrder.`Section` = %d
            ORDER BY GroupNumber, OverallResult",
            $sectid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $current = null;
   $out = array();
   $group = null;

   while (($row = mysql_fetch_assoc($result)) !== false) {
      $row['FirstName'] = data_GetOne($row['UserFirstName'], $row['pFN']);
      $row['LastName'] = data_GetOne($row['UserLastName'], $row['pLN']);

      if ($row['GroupNumber'] != $current) {
         if (count($group))
            $out[] = $group;
         $group = $row;
         $group['People'] = array();
         $current = $row['GroupNumber'];
         $group['GroupId'] = sprintf("%d-%d", $row['Section'], $row['GroupNumber']);

         if ($row['StartingHole'])
            $group['DisplayName'] = $row['StartingHole'];
         else
            $group['DisplayName'] = date('H:i', $row['StartingTime']);
      }
      $group['People'][] = $row;
   }

   if (count($group))
      $out[] = $group;

   mysql_free_result($result);

   return $out;
}


function InsertGroup($group)
{
   foreach ($group['People'] as $player) {
      $query = format_query("INSERT INTO :StartingOrder
                       (Player, StartingTime, StartingHole, GroupNumber, Section)
                     VALUES (%d, FROM_UNIXTIME(%d), %s, %d, %d)",
                     $player['PlayerId'],
                     $group['StartingTime'],
                     esc_or_null($group['StartingHole'], 'int'),
                     $group['GroupNumber'],
                     $group['Section']);
      execute_query($query);
   }
}


function InsertGroupMember($data)
{
   $query = format_query("INSERT INTO :StartingOrder
                    (Player, StartingTime, StartingHole, GroupNumber, Section)
                    VALUES (%d, FROM_UNIXTIME(%d), %s, %d, %d)",
                    $data['Player'],
                    $data['StartingTime'],
                    esc_or_null($data['StartingHole'], 'int'),
                    $data['GroupNumber'],
                    $data['Section']);
   execute_query($query);
}


function GetAllRoundResults($eventid)
{
   $query = format_query("SELECT :RoundResult.id, `Round`, Result, Penalty, SuddenDeath, Completed, Player, PlusMinus, DidNotFinish
                     FROM :RoundResult
                    INNER JOIN `:Round` ON `:Round`.id = :RoundResult.`Round`
                    WHERE `:Round`.Event = %d", $eventid);
   $result = execute_query($query);

   $out = array();
   if ($result) {
      while (($row = mysql_fetch_assoc($result)) !== false)
         $out[] = $row;
   }
   mysql_free_result($result);

   return $out;
}


function GetHoleResults($rrid)
{
   $query = format_query("SELECT Hole, Result FROM :HoleResult WHERE RoundResult = %d", $rrid);
   $result = execute_query($query);

   $out = array();
   if ($result) {
      while (($row = mysql_fetch_assoc($result)) !== false)
         $out[] = $row;
   }
   mysql_free_result($result);

   return $out;
}


function GetAllParticipations($eventid)
{
   $query = format_query("SELECT Classification, :Classification.Name,
                    :Participation.Player, :Participation.id,
                    :Participation.Standing, :Participation.DidNotFinish, :Participation.TournamentPoints,
                    :Participation.OverallResult
                    FROM :Participation
                    INNER JOIN :Classification ON :Classification.id = :Participation.Classification
                    WHERE Event = %d AND EventFeePaid IS NOT NULL", $eventid);
   $result = execute_query($query);

   $out = array();
   if ($result) {
      while (($row = mysql_fetch_assoc($result)) !== false)
         $out[] = $row;
   }
   mysql_free_result($result);

   return $out;
}


function SaveParticipationResults($entry)
{
   $query = format_query("UPDATE :Participation
                        SET OverallResult = %d,
                        Standing = %d,
                        DidNotFinish = %d,
                        TournamentPoints = %d
                     WHERE id = %d",
                     $entry['OverallResult'],
                     $entry['Standing'],
                     $entry['DidNotFinish'],
                     $entry['TournamentPoints'],
                     $entry['id']
                     );
   execute_query($query);
}


function data_CreateSortOrder($desiredOrder, $fields)
{
   if (trim($desiredOrder) == "")
      return '1';

   $bits = explode(',', $desiredOrder);
   $out = array();

   foreach ($bits as $index => $bit) {
      $ascending = true;
      if ($bit != '' && $bit[0] == '-') {
         $ascending = false;
         $bit = substr($bit, 1);
      }

      $field = null;
      $field = @$fields[$bit];

      if (!$field) {
        if (data_string_in_array($bit, $fields))
           $field = $bit;
        else {
            echo $bit;
            return Error::notImplemented();
        }
      }

      if ($field === true)
         $field = $bit;

      if (is_array($field)) {
        if (!$ascending)
          foreach ($field as $k => $v)
            $field[$k] = "-" . $v;
         $bits[$index] = implode(',' , $field);
         $newbits = implode(',', $bits);

         return data_CreateSortOrder($newbits, $fields);
      }

      if ($field[0] == '-')
         $ascending = !$ascending;

      if (strpos($field, "(") !== false)
        $out[] = $field . ' ' . ($ascending ? '' : ' DESC');
      else
        $out[] = '`' . escape_string($field) . '`' . ($ascending ? '' : ' DESC') ;
   }

   return implode(', ', $out);
}


function CreateFileRecord($filename, $displayname, $type)
{
   $query = format_query("INSERT INTO :File (Filename, DisplayName, Type) VALUES
                  ('%s', '%s', '%s')",
                  escape_string($filename),
                  escape_string($displayname),
                  escape_string($type));
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_insert_id();
}


function GetFile($id)
{
   require_once 'core/files.php';

   $query = format_query("SELECT id, Filename, Type, DisplayName FROM :File WHERE id = %d", $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $row = mysql_fetch_Assoc($result);
   mysql_free_result($result);

   if ($row)
     return new File($row);
}


function GetFilesOfType($type)
{
   require_once 'core/files.php';

   $query = format_query("SELECT id, Filename, Type, DisplayName FROM :File WHERE Type = '%s' ORDER BY DisplayName", mysql_real_escape_string($type));
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $retValue = array();
   while (($row = mysql_fetch_Assoc($result)) !== false)
     $retValue[] = new File($row);
   mysql_free_result($result);

   return $retValue;
}


function SaveAd($ad)
{
   $query = format_query("UPDATE :AdBanner SET URL = %s, ImageURL = %s, LongData = %s, ImageReference = %s, Type = %s WHERE id = %d",
                  esc_or_null($ad->url),
                  esc_or_null($ad->imageURL),
                  esc_or_null($ad->longData),
                  esc_or_null($ad->imageReference),
                  esc_or_null($ad->type),
                  $ad->id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);
}


function AnyGroupsDefined($roundid)
{
   $query = format_query("SELECT 1
                  FROM :StartingOrder
                  INNER JOIN :Section ON :Section.id = :StartingOrder.Section
                  INNER JOIN `:Round` ON `:Round`.id = :Section.`Round`
                  WHERE `:Round`.id = %d LIMIT 1", $roundid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_num_rows($result) > 0;
}


function GetRoundGroups($roundid)
{
   $query = format_query("SELECT GroupNumber, StartingTime, StartingHole, :Classification.Name ClassificationName,
                     :Player.lastname LastName, :Player.firstname FirstName, :User.id UserId, :Participation.OverallResult
                  FROM :StartingOrder
                  INNER JOIN :Section ON :Section.id = :StartingOrder.Section
                  INNER JOIN `:Round` ON `:Round`.id = :Section.`Round`
                  INNER JOIN :Player ON :StartingOrder.Player = :Player.player_id
                  INNER JOIN :User ON :User.Player = :Player.player_id
                  INNER JOIN :Participation ON (:Participation.Player = :Player.player_id AND :Participation.Event = :Round.Event)
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  WHERE `:Round`.id = %d
                  ORDER BY GroupNumber, :StartingOrder.id", $roundid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = array();
   while (($row = mysql_fetch_array($result)) !== false)
      $out[] = $row;
   mysql_free_result($result);

   return $out;
}


function GetSingleGroup($roundid, $playerid)
{
   $query = format_query("SELECT :StartingOrder.GroupNumber, UNIX_TIMESTAMP(:StartingOrder.StartingTime) StartingTime, :StartingOrder.StartingHole,
                     :Classification.Name ClassificationName,
                     :Player.lastname LastName, :Player.firstname FirstName, :User.id UserId
                  FROM :StartingOrder
                  INNER JOIN :Section ON :Section.id = :StartingOrder.Section
                  INNER JOIN `:Round` ON `:Round`.id = :Section.`Round`
                  INNER JOIN :Player ON :StartingOrder.Player = :Player.player_id
                  INNER JOIN :User ON :User.Player = :Player.player_id
                  INNER JOIN :Participation ON (:Participation.Player = :Player.player_id AND :Participation.Event = :Round.Event)
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  INNER JOIN :StartingOrder BaseGroup ON (:StartingOrder.Section = BaseGroup.Section
                                                       AND :StartingOrder.GroupNumber = BaseGroup.GroupNumber)
                  WHERE `:Round`.id = %d AND BaseGroup.Player = %d
                  ORDER BY GroupNumber, :StartingOrder.id", $roundid, $playerid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = array();
   while (($row = mysql_fetch_array($result)) !== false)
      $out[] =$row;
   mysql_free_result($result);

   return $out;
}


function GetSingleGroupByPN($roundid, $groupNumber)
{
   $query = format_query("SELECT :StartingOrder.GroupNumber, :StartingOrder.StartingTime, :StartingOrder.StartingHole,
                     :Classification.Name ClassificationName,
                     :Player.lastname LastName, :Player.firstname FirstName, :User.id UserId
                  FROM :StartingOrder
                  INNER JOIN :Section ON :Section.id = :StartingOrder.Section
                  INNER JOIN `:Round` ON `:Round`.id = :Section.`Round`
                  INNER JOIN :Player ON :StartingOrder.Player = :Player.player_id
                  INNER JOIN :User ON :User.Player = :Player.player_id
                  INNER JOIN :Participation ON (:Participation.Player = :Player.player_id AND :Participation.Event = :Round.Event)
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  WHERE `:Round`.id = %d AND GroupNumber = %d
                  ORDER BY GroupNumber, :StartingOrder.id", $roundid, $groupNumber);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = array();
   while (($row = mysql_fetch_array($result)) !== false)
      $out[] =$row;
   mysql_free_result($result);

   return $out;
}


function GetUserGroupSummary($eventid, $playerid)
{
   $query = format_query("SELECT :StartingOrder.GroupNumber, UNIX_TIMESTAMP(:StartingOrder.StartingTime) StartingTime, :StartingOrder.StartingHole,
                     :Classification.Name ClassificationName, :Round.GroupsFinished,
                     :Player.lastname LastName, :Player.firstname FirstName, :User.id UserId
                  FROM :StartingOrder
                  INNER JOIN :Section ON :Section.id = :StartingOrder.Section
                  INNER JOIN `:Round` ON `:Round`.id = :Section.`Round`
                  INNER JOIN :Player ON :StartingOrder.Player = :Player.player_id
                  INNER JOIN :User ON :User.Player = :Player.player_id
                  INNER JOIN :Participation ON (:Participation.Player = :Player.player_id AND :Participation.Event = :Round.Event)
                  INNER JOIN :Classification ON :Participation.Classification = :Classification.id
                  WHERE `:Round`.Event = %d AND :StartingOrder.Player = %d
                  ORDER BY `:Round`.StartTime", $eventid, $playerid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = array();
   while (($row = mysql_fetch_array($result)) !== false)
      $out[] =$row;
   mysql_free_result($result);

   if (!count($out))
      return null;

   return $out;
}


function GetRoundCourse($roundid)
{
   $query = format_query("SELECT :Course.id, Name, Description, Link, Map
                  FROM :Course
                  INNER JOIN `:Round` ON `:Round`.Course = :Course.id
                  WHERE `:Round`.id = %d", $roundid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_fetch_assoc($result);
}


function SetParticipantClass($eventid, $playerid, $newClass)
{
   $query = format_query("UPDATE :Participation SET Classification = %d WHERE Player = %d AND Event = %d",
                 $newClass, $playerid, $eventid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   return mysql_affected_rows() == 1;
}


function GetCourses()
{
   $query = format_query("SELECT id, Name, Event FROM :Course ORDER BY Name");
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = array();
   while (($row = mysql_fetch_assoc($result)) !== false)
      $out[] = $row;
   mysql_free_result($result);

   return $out;
}


function GetCourseDetails($id)
{
   $query = format_query("SELECT id, Name, Description, Link, Map, Event FROM :Course WHERE id = %d", $id);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($query);

   $out = null;
   while (($row = mysql_fetch_assoc($result)) !== false)
      $out = $row;
   mysql_free_result($result);

   return $out;
}


function SaveCourse($course)
{
   if ($course['id']) {
      $query = format_query("UPDATE :Course
                          SET Name = '%s',
                          Description = '%s',
                          Link = '%s',
                          Map = '%s'
                          WHERE id = %d",
                          mysql_real_escape_string($course['Name']),
                          mysql_real_escape_string($course['Description']),
                          mysql_real_escape_string($course['Link']),
                          mysql_real_escape_string($course['Map']),
                          $course['id']);
      $result = execute_query($query);

      if (!$result)
         return Error::Query($query);
   }
   else {
      $eventid = @$course['Event'];
      if (!$eventid)
         $eventid = null;

      $query = format_query("INSERT INTO :Course (Name, Description, Link, Map, Event)
                          VALUES ('%s', '%s', '%s', '%s', %s)",
                          mysql_real_escape_string($course['Name']),
                          mysql_real_escape_string($course['Description']),
                          mysql_real_escape_string($course['Link']),
                          mysql_real_escape_string($course['Map']),
                          esc_or_null($eventid, 'int'));
      $result = execute_query($query);

      if (!$result)
         return Error::Query($query);

      return mysql_insert_id();
   }
}


function StorePayments($payments)
{
   foreach ($payments as $userid => $payments) {
      $user = GetUserDetails($userid);
      $playerid = $user->player;

      if (isset($payments['license'])) {
         foreach ($payments['license'] as $year => $paid) {
            $query = format_query("DELETE FROM :LicensePayment WHERE Player = %d AND Year = %d", $playerid, $year);
            execute_query($query);

            if ($paid) {
               $query = format_query("INSERT INTO :LicensePayment (Player, Year) VALUES (%d, %d)", $playerid, $year);
               execute_query($query);
            }
         }
     }

     if (isset($payments['membership'])) {
         foreach ($payments['membership'] as $year => $paid) {
            $query = format_query("DELETE FROM :MembershipPayment WHERE Player = %d AND Year = %d", $playerid, $year);
            execute_query($query);

            if ($paid) {
               $query = format_query("INSERT INTO :MembershipPayment (Player, Year) VALUES (%d, %d)", $playerid, $year);
               execute_query($query);
            }
         }
      }
   }
}


function EventRequiresFees($eventid)
{
   $query = format_query("SELECT FeesRequired FROM :Event WHERE id = %d", $eventid);
   $result = execute_query($query);

   $row = mysql_fetch_assoc($result);
   mysql_free_result($result);

   return $row['FeesRequired'];
}


function GetTournamentData($tid)
{
   $query = format_query("SELECT player_id, :TournamentStanding.OverallScore, :TournamentStanding.Standing,
                         :Participation.TournamentPoints, :Participation.Classification,
                         :TournamentStanding.id TSID, :Player.player_id PID, :Tournament.id TID,
                         :Event.ResultsLocked, TieBreaker, :Participation.Standing EventStanding
                         FROM :Tournament
                         LEFT JOIN :Event ON :Event.Tournament = :Tournament.id
                         LEFT JOIN :Participation ON :Event.id = :Participation.Event
                         LEFT JOIN :Player ON :Participation.Player = :Player.player_id
                         LEFT JOIN :TournamentStanding ON (:TournamentStanding.Tournament = :Tournament.id
                             AND :TournamentStanding.Player = :Player.player_id)
                         WHERE :Tournament.id = %d
                         ORDER BY :Player.player_id", $tid);
   $result = execute_query($query);

   if (!$result)
      return Error::Query($results);

   $lastrow = null;
   $out = array();
   while (($row = mysql_fetch_assoc($result)) !== false) {
     if (!$lastrow || $row['player_id'] != $lastrow['player_id']) {
         if ($lastrow)
             $out[$lastrow['player_id']] = $lastrow;
         $lastrow = $row;
         $lastrow['Events'] = array();
     }
     $lastrow['Events'][] = $row;
   }

   if ($lastrow)
      $out[$lastrow['player_id']] = $lastrow;

   mysql_free_result($result);

   return $out;
}


function SaveTournamentStanding($item)
{
   if ((int) $item['PID'] == 0)
     return;

   if (!$item['TSID']) {
      $query = format_query("INSERT INTO :TournamentStanding (Player, Tournament, OverallScore, Standing)
                         VALUES (%d, %d, 0, NULL)", $item['PID'], $item['TID']);
      execute_query($query);
   }

   $query = format_query("UPDATE :TournamentStanding
                     SET OverallScore = %d, Standing = %d
                     WHERE Player = %d AND Tournament = %d",
                     $item['OverallScore'],
                     $item['Standing'],
                     $item['PID'],
                     $item['TID']);
   execute_query($query);
}


function UserParticipating($eventid, $userid)
{
   $query = format_query("SELECT :Participation.id FROM :Participation
                     INNER JOIN :Player ON :Participation.Player = :Player.player_id
                     INNER JOIN :User ON :User.Player = :Player.player_id
                     WHERE :User.id = %d AND :Participation.Event = %d",
                     $userid, $eventid);
   $result = execute_query($query);

   return (mysql_num_rows($result) > 0);
}


function UserQueueing($eventid, $userid)
{
   $query = format_query("SELECT :EventQueue.id FROM :EventQueue
                     INNER JOIN :Player ON :EventQueue.Player = :Player.player_id
                     INNER JOIN :User ON :User.Player = :Player.player_id
                     WHERE :User.id = %d AND :EventQueue.Event = %d",
                     $userid, $eventid);
   $result = execute_query($query);

   return (mysql_num_rows($result) > 0);
}


function GetAllToRemind($eventid)
{
   $query = format_query("SELECT :User.id FROM :User
      INNER JOIN :Player ON :User.Player = :Player.player_id
      INNER JOIN :Participation ON :Player.player_id = :Participation.Player
      WHERE :Participation.Event = %d AND :Participation.EventFeePaid IS NULL", $eventid);
   $result = execute_query($query);

   $out = array();
   if ($result) {
      while (($row = mysql_fetch_assoc($result)) !== false)
         $out[] = $row['id'];
   }
   mysql_free_result($result);

   return $out;
}


function data_FinalizeResultSort($roundid, $data)
{
   $needMoreInfoOn = array();

   foreach ($data as $results) {
      $lastRes = -1;
      $lastPlayer = -1;
      $added = false;

      foreach ($results as $player) {
         if ($player['CumulativeTotal'] == $lastRes) {
            if (!$added)
               $needMoreInfoOn[] = $lastPlayer;
            $added = true;
            $needMoreInfoOn[] = $player['PlayerId'];
         }
         else {
            $lastRes = $player['CumulativeTotal'];
            $lastPlayer = $player['PlayerId'];
            $added = false;
         }
      }
   }

   global $data_extraSortInfo;
   $data_extraSortInfo = data_GetExtraSortInfo($roundid, $needMoreInfoOn);

   $out = array();
   foreach ($data as $cn => $results) {
      usort($results, 'data_Result_Sort');
      $out[$cn] = $results;
   }

   return $out;
}


function data_GetExtraSortInfo($roundid, $playerList)
{
   if (!count($playerList))
      return array();

   $ids = array_filter($playerList, 'is_numeric');
   $ids = implode(',', $ids);

   $query = format_query(
     "SELECT `:Round`.id RoundId, :StartingOrder.id StartId, :RoundResult.Result, :StartingOrder.Player
         FROM `:Round` LinkRound INNER JOIN `:Round` ON `:Round`.Event = LinkRound.Event
         INNER JOIN :Section ON :Section.`Round` = `:Round`.id
         INNER JOIN :StartingOrder ON :StartingOrder.Section = :Section.id
         INNER JOIN :RoundResult ON (:RoundResult.`Round` = `:Round`.id AND :RoundResult.Player = :StartingOrder.Player)
         WHERE :StartingOrder.Player IN (%s) AND `:Round`.id <= %d AND LinkRound.id = %d
         ORDER BY :Round.StartTime, :StartingOrder.Player", $ids, $roundid, $roundid);
   $result = execute_query($query);

   $out = array();
   while (($row = mysql_fetch_assoc($result)) !== false) {
      if (!isset($out[$row['RoundId']]))
         $out[$row['RoundId']] = array();
      $out[$row['RoundId']]  [$row['Player']] = $row;
   }
   mysql_free_result($result);

   return array_reverse($out);
}


function data_Result_Sort($a, $b)
{
   $dnfa = (bool) $a['DidNotFinish'];
   $dnfb = (bool) $b['DidNotFinish'];
   if ($dnfa != $dnfb) {
      if ($dnfa)
         return 1;
      return -1;
   }

   $compa = $a['Completed'];
   $compb = $b['Completed'];
   if ($compa != $compb && ($compa == 0 || $compb == 0)) {
      if ($compa == 0)
         return 1;
      return -1;
   }

   $cpma = $a['CumulativePlusminus'];
   $cpmb = $b['CumulativePlusminus'];
   if ($cpma != $cpmb) {
      if ($cpma > $cpmb)
         return 1;
      return -1;
   }

   $sda = $a['SuddenDeath'];
   $sdb = $b['SuddenDeath'];
   if ($sda != $sdb) {
      if ($sda < $sdb)
         return -1;
      return 1;
   }

   global $data_extraSortInfo;
   foreach ($data_extraSortInfo as $round) {
      $ad = @$round[$a['PlayerId']];
      $bd = @$round[$b['PlayerId']];

      if ($ad == null && $bd == null)
         continue;
      if ($ad == null || $bd == null) {
         if ($ad == null)
            return 1;
         return -1;
      }

      if ($ad['Result'] != $bd['Result']) {
         if ($ad['Result'] < $bd['Result'])
            return -1;
         return 1;
      }
   }

   foreach ($data_extraSortInfo as $round) {
      $ad = @$round[$a['PlayerId']];
      $bd = @$round[$b['PlayerId']];

      if ($ad == null && $bd == null)
         continue;

      if ($ad['StartId'] < $bd['StartId'])
         return -1;

      return 1;
   }
}


function SetRoundGroupsDone($roundid, $done)
{
   $time = null;
   if ($done)
      $time = time();

   $query = format_query("UPDATE `:Round` SET GroupsFinished = FROM_UNIXTIME(%s) WHERE id = %d", esc_or_null($time, 'int' ), $roundid);
   execute_query($query);
}


function data_string_in_array($string, $array)
{
   foreach ($array as $value)
      if ($string === $value)
         return true;

   return false;
}


/**
*Determines if license and membership fees have been paid for a given year
* Suggested usage:
* list($license, $membership) = GetUserFees($playerid, $year);
*/
function GetUserFees($playerid, $year)
{
   $query = format_query("SELECT 1 FROM :LicensePayment WHERE Player = %d AND Year = %d",
                      $playerid, $year);
   $result = execute_query($query);
   $license = mysql_num_rows($result);
   mysql_free_result($result);

   $query = format_query("SELECT 1 FROM :MembershipPayment WHERE Player = %d AND Year = %d",
                      $playerid, $year);
   $result = execute_query($query);
   $membership = mysql_num_rows($result);
   mysql_free_result($result);

   return array($license, $membership);
}


function GetRegisteringEvents()
{
   $now = time();
   return data_GetEvents("SignupStart < FROM_UNIXTIME($now) AND SignupEnd > FROM_UNIXTIME($now)", "SignupEnd");
}


function GetRegisteringSoonEvents()
{
   $now = time();
   $twoweeks = time() + 21*24*60*60;

   return data_GetEvents("SignupStart > FROM_UNIXTIME($now) AND SignupStart < FROM_UNIXTIME($twoweeks)", "SignupStart");
}


function GetUpcomingEvents($onlySome)
{
   $data = data_GetEvents("Date > FROM_UNIXTIME(" . time() . ')');
   if ($onlySome)
      $data = array_slice($data, 0, 10);

   return $data;
}


function GetPastEvents($onlySome, $onlyYear = null)
{
   $thisYear = "";
   if ($onlyYear != null)
      $thisYear = "AND YEAR(Date) = $onlyYear";

   $data = data_GetEvents("Date < FROM_UNIXTIME(" . time() . ") $thisYear AND ResultsLocked IS NOT NULL");
   $data = array_reverse($data);

   if ($onlySome)
      $data = array_slice($data, 0, 5);

   return $data;
}


function DeleteTextContent($id)
{
   $query = format_query("DELETE FROM :TextContent WHERE id = %d", $id);
   execute_query($query);
}


function DeleteCourse($id)
{
   $query = format_query("DELETE FROM :Hole WHERE Course = %d", $id);
   execute_query($query);

   $query = format_query("DELETE FROM :Course WHERE id = %d", $id);
   execute_query($query);
}


function SetTournamentTieBreaker($tournament, $player, $value)
{
   $query = format_query("UPDATE :TournamentStanding SET TieBreaker = %d WHERE Player = %d AND Tournament = %d",
                     $value, $player, $tournament);
   execute_query($query);
}


function data_sort_leaderboard($a, $b)
{
   $ac = $a['Classification'];
   $bc = $b['Classification'];
   if ($ac != $bc) {
      if ($ac < $bc)
         return -1;
      return 1;
   }

   $astand = $a['Standing'];
   $bstand = $b['Standing'];
   if ($astand != $bstand) {
      if ($astand < $bstand)
         return -1;
      return 1;
   }

   $asd = $a['SuddenDeath'];
   $bsd = $b['SuddenDeath'];
   if ($asd != $bsd) {
      if ($asd < $bsd)
         return -1;
      return 1;
   }

   $ar = $a['Results'];
   $br = $b['Results'];

   $keys = array_reverse(array_keys($ar));
   foreach ($keys as $key) {
      $ae = $ar[$key]['Total'];
      $be = $br[$key]['Total'];
      if ($ae != $be) {
         if ($ae < $be)
            return -1;
         return 1;
      }
   }

   $as = $ar[$keys[0]]['StartId'];
   $bs = $br[$keys[0]]['StartId'];
   if ($as < $bs)
      return -1;
   return 1;
}


function RemovePlayersDefinedforAnySection($a)
{
   list($round, $section) = $GLOBALS['RemovePlayersDefinedforAnySectionRound'];

   static $data;
   if (!is_array($data))
      $data = array();

   $key = sprintf("%d_%d", $round, $section);
   if (!isset($data[$key])) {
      $query = format_query("SELECT Player FROM :StartingOrder
                          INNER JOIN :Section ON :StartingOrder.Section = :Section.id
                          WHERE :Section.Round = %d", $round);
      $result = execute_query($query);

      $mydata = array();
      while (($row = mysql_fetch_assoc($result)) !== false)
         $mydata[$row['Player']] = true;
      $data[$key] = $mydata;
      mysql_free_result($result);
   }

   return !@$data[$key][$a['PlayerId']];
}


function DeleteEventPermanently($event)
{
   $id = $event->id;

   $queries = array();
   $queries[] = format_query("DELETE FROM :AdBanner WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :EventQueue WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :ClassInEvent WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :EventManagement WHERE Event = %d", $id);

   $rounds = $event->GetRounds();
   foreach ($rounds as $round) {
      $rid = $round->id;
      $sections = GetSections($rid);
      foreach ($sections as $section) {
         $sid = $section->id;

         $queries[] = format_query("DELETE FROM :SectionMembership WHERE Section = %d", $sid);
         $queries[] = format_query("DELETE FROM :StartingOrder WHERE Section = %d", $sid);
      }
      $queries[] = format_query("DELETE FROM :Section WHERE Round = %d", $rid);

      $query = format_query("SELECT id FROM :RoundResult WHERE Round = %d", $rid);
      $result = execute_query($query);

      while (($row = mysql_fetch_assoc($result)) !== false)
         $queries[] = format_query("DELETE FROM :HoleResult WHERE RoundResult = %d", $row['id']);
      mysql_free_result($result);

      $queries[] = format_query("DELETE FROM :RoundResult WHERE Round = %d", $rid);
   }

   $queries[] = format_query("DELETE FROM :Round WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :TextContent WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :Participation WHERE Event = %d", $id);
   $queries[] = format_query("DELETE FROM :Event WHERE id = %d", $id);

   foreach ($queries as $query)
      execute_query($query);
}


function data_fixNameCase($name)
{
   $string = ucwords(strtolower($name));

   foreach (array('-', '\'') as $delimiter) {
      if (strpos($string, $delimiter)!==false)
         $string =implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
   }

   return $string;
}
