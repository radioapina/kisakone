<?php
require_once('../../../config.php');

function InitializeDatabaseConnection()
{
  global $settings;
  $con = @mysql_connect($settings['DB_ADDRESS'], $settings['DB_USERNAME'], $settings['DB_PASSWORD']);

  if (!($con && @mysql_select_db($settings['DB_DB']))) {
    die("Unable to connect to DB...");
  }
}


function FixQuery($query) {
  global $settings;
  $prefix = $settings['DB_PREFIX'];
  $query = trim($query);

  if (!$query || $query == 'SHOW WARNINGS')
    return $query;

  return str_replace(':', $prefix, $query);
}


function RunQuery($query) {
  $query = FixQuery($query);

  if (empty($query))
    return 0;

  if (!mysql_query($query)) {
    echo "Query: $query\n";
    echo "Error: ", mysql_error(), "\n";
    die();
  }
}


function Upgrade($file = "upgrade.sql") {
  echo "Running upgrade from $file: ";
  $source = file_get_contents($file);
  $queries = explode(';', $source);

  foreach ($queries as $query) {
    RunQuery($query);
    echo ".";
  }
  echo "\n";
}


function MigratePlayerLimit() {
  global $settings;

  $query = FixQuery("SELECT id, PlayerLimit From :Event;");
  $res = mysql_query($query);
  if (mysql_num_rows($res) > 0) {
    while ($assoc = mysql_fetch_assoc($res)) {
      $id = $assoc['id'];
      echo "Info: Migrating Event id $id\n";
      $limit = $assoc['PlayerLimit'];
      if ($limit == "") {
        echo "Warning: PlayerLimit is '' for Event id $id, setting to 0\n";
        $limit = 0;
      }

      RunQuery("INSERT INTO :Pool (Event, PlayerLimit) VALUES($id, $limit);");
      RunQuery("UPDATE :Event SET PoolCount = 1 WHERE id = $id;");
    }
  }
  else {
    echo "Query: $query\n";
    echo "Error: No rows selected?\n";
    die();
  }
}


# Execute upgrade
InitializeDatabaseConnection();
Upgrade('upgrade.sql');
MigratePlayerLimit();
Upgrade('upgrade2.sql');
