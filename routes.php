<?php
$action = $_GET["action"];

include('config.php');

$postdata = file_get_contents("php://input");
$params = json_decode($postdata);

include('parameters.php');
include('competition.php');
include('match.php');
include('player.php');
include('sponsors.php');
include('statistics.php');
include('team.php');
include('discord.php');

switch ($action) {
    case "archives":
        $competitions = competition_fetch_all($con,0);
        echo json_encode($competitions);
        break;
    case "boot":
        $league = new stdClass();
        $league->parameters = parameters($con);
        $league->stats = league($con);
        $league->competitions = competition_fetch_all($con,1);
        $league->user = $user->data;
        $league->coach = $coach;
        echo json_encode($league,JSON_NUMERIC_CHECK);
        break;
    case "competition":
        $competition = competition_fetch($con, $params->id, 1);
        echo json_encode($competition,JSON_NUMERIC_CHECK);
        break;
    case "competitionAdd":
        competition_add($con, $Cyanide_Key, $params);
        break;
    case "competitionCalendar":
        competition_calendar($con, $params[0]);
        break;
    case "competitionUpdate":
        competition_update($con, $Cyanide_Key, $Cyanide_League, $params);
        $competition = competition_fetch($con, $params[1]);
        echo json_encode($competition,JSON_NUMERIC_CHECK);
        break;
    case "match":
        $match = match_fetch($con, $params[0]);
        echo json_encode($match,JSON_NUMERIC_CHECK);
        break;
    case "matchDate":
        vue_match_set_date($con, $params);
        break;
    case "matchReset":
        match_save($con, $Cyanide_Key, $params, 1);
        break;
    case "team":
        team_fetch($con,$params[0]);
        break;
    case "teamUpdate":
        team_update($con, $Cyanide_Key, $params->id);
        team_fetch($con, $params[0]);
        echo json_encode($team,JSON_NUMERIC_CHECK);
        break;
    case "sponsors":
        $sponsors = sponsor_fetch_all($con);
        echo json_encode($sponsors);
        break;
    case "sponsorsMatch":
        $competition = sponsorsMatch_fetch($con, $params[0], 1);
        echo json_encode($competition,JSON_NUMERIC_CHECK);
        break;
    case "sponsorsCalendar":
        sponsors_calendar($con, $params[0]);
        break;
    case "sponsorsStanding":
        sponsors_standing($con, $params[0]);
        break;
    case "upcomingGames":
        upcomingGames($con);
        break;
    case "lastGames":
        lastGames($con);
        break;
    case "alltojson":
        save_all_to_json($con, $Cyanide_Key);
        break;
    default:
        echo "Erreur!";
        break;
};
?>
