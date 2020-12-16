<?php

$action = $_GET["action"];

include('config.php');

$postdata = file_get_contents("php://input");
$params = json_decode($postdata);

include('competition.php');
include('season.php');
include('forum.php');
include('player.php');
include('team.php');
include('tools/tool.php');


switch ($action) {
  case "forumProfilesUpdate":
    forum_profiles_update($con);
    break;
  case "getIngameCompetitions":
    ingame_competition_fetch_all($con, $Cyanide_Key, $Cyanide_League);
    break;
  case "getCompetitionsForums":
    forum_fetch_all($con,$params[0]);
    break;
  case "competitionAdd":
    competition_add($con, $Cyanide_Key, $params);
    forum_links_add($con, $params);
    break;
  case "seasonArchive":
    champion_set($con);
    season_archive($con);
    break;
  case "saveStandings":
    save_standings($con);
    break;
  default:
    echo "Erreur!";
    break;
};


?>
