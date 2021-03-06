<?php
//Get all competitions
//Get competition
/**
 * @param $con : DB connection, mysqli
 * @param $active : is competition active, Boolean
 */
function competition_fetch_all($con, $active){
    $competitions = [];
    $sql = "SELECT id FROM site_competitions WHERE active=".$active." AND competition_id_parent IS NULL ORDER BY started DESC";
    $result = $con->query($sql);

    while( $ids = $result->fetch_row()){
      $competition = competition_fetch($con, $ids[0], 0);
      array_push($competitions, $competition);
    }
    return $competitions;
};

//Get competition
/**
 * @param $con : DB connection, mysqli
 * @param $id : competition DB ID, Integer
 * @param $stats : fetch statistics, Boolean
 */
function competition_fetch($con, $id, $stats){
    $sql = "SELECT c.id, c.competition_id_parent, c.league_name AS division, c.game, c.started, c.pool, c.rounds_count, c.site_name,
    c.site_order, c.season, c.active, c.competition_mode, c.game_name, c.champion, c.param_name_format AS format,
    (SELECT COUNT(*) FROM site_matchs WHERE competition_id=".$id." AND cyanide_id IS NULL) AS matchsLeft,
    (SELECT MAX(round) FROM site_matchs WHERE competition_id=".$id.") AS lastRound
    FROM site_competitions AS c WHERE c.id = ".$id;
    $result = $con->query($sql);
    $competition = $result->fetch_object();
    if($competition->competition_mode!='Sponsors'){
        if($competition->active == 1){
            $competition->standing = competition_standings($con, $id);
        }
        else {
            $competition->standing = archives_standings($con, $id);
        }
        if($stats == 1){
          $competition = competition_stats($con, $competition);
        }
    }
    else{
        $competition->standing = sponsors_standing($con, $id);
        $competition = sponsors_stats($con, $competition);
    };

    return $competition;

};

//Get standing
/**
 * @param $con : DB connection, mysqli
 * @param $id : competition DB ID, Integer
 */
function competition_standings($con, $id){
    $standings = [];
    $sqlStandings = "SELECT
          $id as competition_id,
          season,
          competition_name,
          champion AS champion,
          id AS team_id,
          cyanide_id AS team_cyanide_id,
          name AS team_name,
          logo AS team_logo,
          race AS team_race,
          color_1 AS team_color_1,
          color_2 AS team_color_2,
          coach_id,
          coach_name,
          SUM( case when score_1 > score_2 then 3 else 0 end + case when score_1 = score_2 AND score_1 IS NOT NULL then 1 else 0 end) AS points,
          COUNT(case when score_1 > score_2 then 1 end) AS win,
          COUNT(case when score_1 = score_2 then 1 end) AS draw,
          COUNT(case when score_2 > score_1 then 1 end) AS loss,
          SUM(score_1) AS touchdowns,
          SUM(score_1) - SUM(score_2) AS touchdowns_diff,
          SUM(sustainedcasualties_2 ) AS casualties,
          SUM(sustainedcasualties_2 ) - SUM(sustainedcasualties_1) AS casualties_diff,
          SUM(score_1) - SUM(score_2) + SUM(sustainedcasualties_2) - SUM(sustainedcasualties_1) AS TDS,
          0 AS confrontation1,
          0 AS confrontation2
          FROM (
            SELECT site_matchs.id AS game, site_teams.id AS id, site_teams.cyanide_id AS cyanide_id, site_teams.logo AS logo, site_teams.param_id_race AS race, site_teams.name AS name, site_teams.color_1 AS color_1, site_teams.color_2 AS color_2,
            site_coachs.id AS coach_id, site_coachs.name AS coach_name,
            site_competitions.site_name AS competition_name, site_competitions.season AS season, site_competitions.champion as champion,
            score_1, score_2, sustainedcasualties_1, sustainedcasualties_2, sustaineddead_1, sustaineddead_2
            FROM site_matchs
            LEFT JOIN site_competitions ON site_competitions.id=site_matchs.competition_id
            LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_1
            INNER JOIN site_coachs ON site_coachs.cyanide_id=site_teams.coach_id
            WHERE competition_id = $id
            UNION
            SELECT site_matchs.id AS game, site_teams.id AS id, site_teams.cyanide_id AS cyanide_id, site_teams.logo AS logo, site_teams.param_id_race AS race, site_teams.name AS name, site_teams.color_1 AS color_1, site_teams.color_2 AS color_2,
            site_coachs.id AS coach_id, site_coachs.name AS coach_name,
            site_competitions.site_name AS competition_name, site_competitions.season AS season, site_competitions.champion as champion,
            score_2, score_1, sustainedcasualties_2, sustainedcasualties_1, sustaineddead_2, sustaineddead_1
            FROM site_matchs
            LEFT JOIN site_competitions ON site_competitions.id=site_matchs.competition_id
            LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_2
            INNER JOIN site_coachs ON site_coachs.cyanide_id=site_teams.coach_id
            WHERE competition_id = $id
          ) AS a
          WHERE LENGTH(coach_id)>0
          GROUP BY id
          ORDER BY points DESC, win DESC, TDS DESC";
    $resultStandings = $con->query($sqlStandings);
    while($dataStandings = $resultStandings->fetch_assoc()) {
        array_push($standings, $dataStandings);
    }

    //Managing exaequo
    for($j = 1; $j <= 2; $j++) {
      $limit = count($standings);

      for($i = 1; $i < $limit; $i++) {
          $row = [1];
          if ( $standings[$i]['points'] == $standings[$i-1]['points'] ) {
              $sqlConfrontation = 'SELECT
              case when score_1 > score_2 then 2 else
              case when score_1 = score_2 AND score_1 IS NOT NULL then 1
              else 0 end end,
              name
              FROM (
                SELECT site_teams.id, site_teams.name, score_1, score_2 FROM site_matchs
                LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_1
                WHERE competition_id = '.$id.' AND site_matchs.team_id_1 = '.$standings[$i]['team_id'].' AND site_matchs.team_id_2 = '.$standings[$i-1]['team_id'].'
                UNION
                SELECT site_teams.id, site_teams.name, score_2, score_1 FROM site_matchs
                LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_2
                WHERE competition_id='.$id.' AND site_matchs.team_id_2 = '.$standings[$i]['team_id'].' AND site_matchs.team_id_1 = '.$standings[$i-1]['team_id'].'
              ) AS a
              GROUP BY id';
              $result = $con->query($sqlConfrontation);

              if(  mysqli_num_rows($result) > 0){
                $row = $result->fetch_row();
              }
              else{
                $row = [0];
              }

            }
        $standings[$i]['confrontation'.$j] = $row[0];
      }

      array_multisort(array_column($standings, 'points'),  SORT_DESC,
                array_column($standings, 'confrontation1'), SORT_DESC,
                array_column($standings, 'confrontation2'), SORT_DESC,
                array_column($standings, 'win'), SORT_DESC,
                array_column($standings, 'TDS'), SORT_DESC,
                $standings);
    }
    return $standings;

};

//Save standings
function competition_standings_save($con, $id){
    $sqlCount = "SELECT count(*) FROM site_competitions_standings WHERE competition_id=$id";
    $result = $con->query($sqlCount);
    $count =$result->fetch_row();
    $standings = competition_standings($con,$id);
    for($i = 0; $i < count($standings); $i++) {
        $j=$i+1;
        $season = $con->real_escape_string($standings[$i]['season']);
        $competition_name = $con->real_escape_string($standings[$i]['competition_name']);
        $team_name = $con->real_escape_string($standings[$i]['team_name']);
        $coach_name = $con->real_escape_string($standings[$i]['coach_name']);
        $matches = $standings[$i]['win']+$standings[$i]['draw']+$standings[$i]['loss'];
        if($count[0] == 0 ){
            $sqlSaveStandings = "INSERT INTO site_competitions_standings
            (season, competition_id, competition_name, champion,
            rank, team_id, team_cyanide_id, team_name, team_logo, team_race, team_colors,
            coach_id, coach_name,
            points, matches, win, draw, loss, touchdowns, touchdowns_diff, casualties, casualties_diff )
            VALUES ('$season',$competition[0], '$competition_name', '".$standings[$i]['champion']."',
              $j,".$standings[$i]['team_id'].",".$standings[$i]['team_cyanide_id'].",'$team_name',".$standings[$i]['team_race'].",'".$standings[$i]['team_logo']."','[\"".$standings[$i]['team_color_1']."\",\"".$standings[$i]['team_color_2']."\"]',
              ".$standings[$i]['coach_id'].",'$coach_name',
              ".$standings[$i]['points'].",$matches,".$standings[$i]['win'].",".$standings[$i]['draw'].",".$standings[$i]['loss'].",".$standings[$i]['touchdowns'].",".$standings[$i]['touchdowns_diff'].",".$standings[$i]['casualties'].",".$standings[$i]['casualties_diff']." )";
        }
        else {
            $sqlSaveStandings = "UPDATE site_competitions_standings
            SET rank = $j,
            points = '".$standings[$i]['points']."',
            win = '".$standings[$i]['win']."',
            draw = '".$standings[$i]['draw']."',
            loss = '".$standings[$i]['loss']."',
            matches = $matches,
            touchdowns = '".$standings[$i]['touchdowns']."',
            touchdowns_diff = '".$standings[$i]['touchdowns_diff']."',
            casualties = '".$standings[$i]['casualties']."',
            casualties_diff = '".$standings[$i]['casualties_diff']."'
            WHERE competition_id=$id AND team_id=".$standings[$i]['id'];
        }
        $con->query($sqlSaveStandings);
    }
}

//Get fixtures
/**
 * @param $con : DB connection, mysqli
 * @param $id : competition DB ID, Integer
 */
function competition_calendar($con, $id){
    $calendar = [];
    $sqlRounds = "SELECT DISTINCT(round) FROM site_matchs WHERE competition_id=".$id." ORDER BY round";
    $resultRounds = $con->query($sqlRounds);
    while($rounds = $resultRounds->fetch_object()){
        $matchs = [];
        $sqlMatchs = "SELECT site_matchs.id, site_matchs.cyanide_id, site_matchs.contest_id, site_matchs.started, round,
          t1.coach_id as coach_id_1, team_id_1, t1.name as name_1, t1.logo as logo_1, score_1,
          t2.coach_id as coach_id_2, team_id_2, t2.name as name_2, t2.logo as logo_2, score_2
          FROM site_matchs
          LEFT JOIN site_teams as t1 ON t1.id=site_matchs.team_id_1
          LEFT JOIN site_teams as t2 ON t2.id=site_matchs.team_id_2
          WHERE competition_id=".$id." AND round=".$rounds->round;
        $resultMatchs = $con->query($sqlMatchs);

        while($dataMatchs = $resultMatchs->fetch_object()) {
            // $prono=[];
            // $dataMatchs->bets=[];
            // $sqlProno="SELECT match_id, coach_id, team_score_1, team_score_2, stake FROM site_bets
            //   WHERE match_id=".$dataMatchs->id;
            // $resultProno = $con->query($sqlProno);
            // while($dataProno = $resultProno->fetch_assoc()) {
            //     $dataMatchs->bets=$dataProno;
            // }
            array_push($matchs, $dataMatchs);
        }

        //Matchs to save
        $matchsToSave = [];
        $sqlMatchsToSave = "SELECT contest_id FROM site_matchs WHERE competition_id=".$id." AND cyanide_id IS NULL AND round=".$rounds->round;
        $resultMatchsToSave = $con->query($sqlMatchsToSave);
        while($dataMatchsToSave = $resultMatchsToSave->fetch_row()) {
          array_push($matchsToSave, $dataMatchsToSave[0]);
        };

        $sqlCurrentDay="SELECT IFNULL(MAX(round),1) FROM site_matchs WHERE started IS NOT NULL AND competition_id=".$id;
        $resultCurrentDay = $con->query($sqlCurrentDay);
        $currentDay = $resultCurrentDay->fetch_row();
        $rounds->currentDay = $currentDay[0];
        $rounds->currentRound = $currentDay[0];
        $rounds->matchs = $matchs;
        $rounds->matchsToSave = $matchsToSave;

        array_push($calendar, $rounds);
    }
    echo json_encode($calendar,JSON_NUMERIC_CHECK);
    die();
};

//Get statistics
/**
 * @param $con : DB connection, mysqli
 * @param $competition : competition to update, Object
 */
function competition_stats($con, $competition){
    //Leaderboard
    $competition->playersStats = [];

    $stats = ['scorer','thrower','tackler','killer','intercepter','catcher','punchingball'];
    foreach($stats as $stat){
        array_push($competition->playersStats, leaders($con,[$stat,$competition->id]));
    }
    return $competition;
};

//Update competition
/**
* @param $con : DB connection, mysqli
* @param $Cyanide_Key : Key for Cyanide's API, String
* @param $Cyanide_League : League name in the game, String
* @param $params : mandatory params Array
*        [0] Competition name, String
*        [1] Competition id, Integer
*        [2] Format, String
*        [3] Round, Integer
*        [4] Matchs to save, Array
 */
function competition_update($con, $Cyanide_Key, $Cyanide_League, $params){
  var_dump($params);
  if($params[2] == 'ladder'){
      competition_ladder_update($con, $Cyanide_Key, $Cyanide_League, $params);
  }
  else {
    if(count($params[4]) != 0){
        competition_update_matchs($con, $Cyanide_Key, $Cyanide_League, $params);
    }
    elseif (in_array($params[2],['swiss','single_elimination'])) {
        competition_next_round($con, $Cyanide_Key, $Cyanide_League, $params);
    }
  }
};

//Update competition's matchs already played (except ladder)
/**
* @param $con : DB connection, mysqli
* @param $Cyanide_Key : Key for Cyanide's API, String
* @param $Cyanide_League : League name in the game, String
* @param $params : mandatory params Array
*        [0] Competition name, String
*        [1] Competition id, Integer
*        [2] Format, String
*        [3] Round, Integer
*        [4] Matchs to save, Array
*/
function competition_update_matchs($con, $Cyanide_Key, $Cyanide_League, $params){

    $request = "https://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&competition=".urlencode($params[0])."&status=played&round=".$params[3];
    $response  = file_get_contents($request);
    $played = json_decode($response);
    //$matchsCount=0;
    foreach ($played->upcoming_matches as $game) {
        if(in_array($game->contest_id, $params[4])){
            match_save($con, $Cyanide_Key, [$game->match_uuid,$game->contest_id], 0);
          //  $matchsCount++;
        }
    }
    // if($matchsCount==count($params[4])){
    //   competition_next_round($con, $Cyanide_Key, $Cyanide_League, $params);
    // }
    //to set
    //payment($con, $params[1]);
};

//Swiss format insert next round
/**
* @param $con : DB connection, mysqli
* @param $Cyanide_Key : Key for Cyanide's API, String
* @param $Cyanide_League : League name in the game, String
* @param $params : mandatory params Array
*        [0] Competition name, String
*        [1] Competition id, Integer
*        [2] Format, String
*        [3] Round, Integer
*        [4] Matchs to save, Array
 */
function competition_next_round($con, $Cyanide_Key, $Cyanide_League, $params){

    $nextRound = $params[3] + 1;
    $request = "https://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&competition=".urlencode($params[0])."&exact=1&status=scheduled&round=".$nextRound;
    $response  = file_get_contents($request);
    $schedule = json_decode($response);
    //Saving matchs
    foreach ($schedule->upcoming_matches as $match) {
        $teams = [];
        foreach ($match->opponents as $key=>$opponent) {
          $sqlTeam = "SELECT id FROM site_teams WHERE cyanide_id = '".$opponent->team->id."'";
          $resultTeam = $con->query($sqlTeam);
          $team = $resultTeam->fetch_row();
          if ( $team[0] == 0){
              $sqlTeam= "INSERT INTO site_teams ( name, cyanide_id, coach_id, active, value, leitmotiv, logo)
              VALUES ('".str_replace("'","\'",$opponent->team->name)."',  '".$opponent->team->id."',  '".$coach_id."', '1','".$opponent->team->value."','".str_replace("'","\'",$opponent->team->motto)."', '".$opponent->team->logo."')";
              $con->query($sqlTeam);
              $teams[$key] = $con->insert_id;
          }
          else {
              $sqlTeam = "UPDATE site_teams SET active=1 WHERE cyanide_id=".$opponent->team->id;
              $con->query($sqlTeam);
              $teams[$key] = $team[0];
          };
      }

      $sqlMatch = "INSERT INTO site_matchs (contest_id, competition_id, round, team_id_1, team_id_2)
      VALUES (".$match->contest_id.",".$params[1].",".$match->round.",".$teams[0].",".$teams[1].")";
      $con->query($sqlMatch);
    }

};

//Insert new matches in a competition
/**
 * @param $con : DB connection, mysqli
 * @param $Cyanide_Key : Key for Cyanide's API, String
 * @param $params : mandatory params Array
 *        [0] Competition name, String
 *        [1] Competition id, Integer
 *        [2] Format, String
 *        [3] Round, Integer
 *        [4] Matchs to save, Array
 * @param $competitionID : competition DB ID, Integer
 * @param $competition : data to save, Array of Objects
 *        Object structure:
 *        round: Round for the matches, Integer
 *        matches: Matches to save, Array of Objects
 */
function competition_add_matchs($con, $Cyanide_Key, $competitionID, $competition){
    $teams = [];
    $coachs = [];
    //Saving matches
    foreach ($competition->matches as $match) {
        if( $match->opponents[0]->coach->id!=$match->opponents[1]->coach->id && ($match->round == 1 || !$competition->round)){
            $match->teamBBBL = [];
            //Test if coach and team exists
            foreach ($match->opponents as $key=>$opponent) {
                $test_coach = $con->query("SELECT id FROM site_coachs WHERE cyanide_id = '".$opponent->coach->id."'")->fetch_row();
                if(in_array($opponent->coach->id,$coachs)==false){
                    if ( $test_coach[0] == 0){
                        $sqlCoach = "INSERT INTO site_coachs ( name, cyanide_id, active ) VALUES ('".$opponent->coach->name."','".$opponent->coach->id."',1)";
                        $con->query($sqlCoach);
                        array_push($coachs,$opponent->coach->id);
                    }
                    else {
                        $sqlCoach = "UPDATE site_coachs SET active=1 WHERE cyanide_id=".$opponent->coach->id;
                        $con->query($sqlCoach);
                        array_push($coachs,$opponent->coach->id);
                    };
                }
                $test_team = $con->query("SELECT id FROM site_teams WHERE cyanide_id = '".$opponent->team->id."'")->fetch_row();
                if(in_array($opponent->team->id,$teams)==false){
                    if ( $test_team[0] == 0){
                        $test_team[0] = team_create($con, $Cyanide_Key, $opponent->team->id);
                        array_push($teams,$opponent->team->id);
                    }
                    else {
                        team_update($con, $Cyanide_Key, $opponent->team->id);
                        array_push($teams,$opponent->team->id);
                    };
                }
                array_push($match->teamBBBL,$test_team[0]);
            }
            //add match
            $sqlMatch = "INSERT INTO site_matchs (contest_id, cyanide_id, competition_id, round, team_id_1, team_id_2)
            VALUES (".$match->contest_id.",'".$match->match_uuid."',".$competitionID.",".$match->round.",".$match->teamBBBL[0].",".$match->teamBBBL[1].")";
            //echo $sqlMatch;
            $con->query($sqlMatch);
        }
    }
};

//Update Ladder competition's matchs
/**
* @param $con : DB connection, mysqli
* @param $Cyanide_Key : Key for Cyanide's API, String
* @param $Cyanide_League : League name in the game, String
* @param $params : mandatory params Array
*        [0] Competition name, String
*        [1] Competition id, Integer
*        [2] Format, String
*        [3] Round, Integer
*        [4] Matchs to save, Array
 */
function competition_ladder_update($con, $Cyanide_Key, $Cyanide_League, $params){
  $sqlLastGame = "SELECT DATE_ADD(MAX(started), INTERVAL + 1 MINUTE ) FROM site_matchs";
  $Lastgame = $con->query($sqlLastGame);
  $DateLastgame = $Lastgame->fetch_row();
  $request = "https://web.cyanide-studio.com/ws/bb2/matches/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1&competition=".urlencode($params[0])."&start=".urlencode($DateLastgame[0]);
  $response  = file_get_contents($request);
  $played = json_decode($response);
  foreach($played->matches as $match){
      $match->match_uuid = $match->uuid;
      $match->contest_id = 'null';
      $match->round=1;
      $match->opponents = [];
      for($i=0;$i<2;$i++){
          $coach = new stdClass;
          $coach->id = $match->coaches[$i]->idcoach;
          $coach->name = $match->coaches[$i]->coachname;
          $team = new stdClass;
          $team->id = $match->teams[$i]->idteamlisting;
          $opponent = new stdClass;
          $opponent->coach = $coach;
          $opponent->team = $team;
          array_push($match->opponents,$opponent);
      }
  };
  competition_add_matchs($con, $Cyanide_Key, $params[1], $played);


  foreach ($played->matches as $game) {
      match_save($con, $Cyanide_Key, [$game->uuid,'null',$params[3]], 0);
  }


};
// Admin functions

//Get all competitions from Cyanide server
/**
 * @param $con : DB connection, mysqli
 * @param $Cyanide_Key : Key for Cyanide's API, String
 * @param $Cyanide_League : League name in the game, String
 */
function ingame_competition_fetch_all($con, $Cyanide_Key, $Cyanide_League){
  $request = "https://web.cyanide-studio.com/ws/bb2/competitions/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1";
  $response = file_get_contents($request);
  $arr = json_decode($response);
  $competitions = array_filter($arr->competitions, function($item){
    if($item->status==1)
      {
          return true;
      }
  });
  echo json_encode($competitions);
};

//Add new competition
/**
 * @param $con : DB connection, mysqli
 * @param $Cyanide_Key : Key for Cyanide's API, String
 * @param $competition : competition to add, object
 */
function competition_add($con, $Cyanide_Key, $competition){
    //Test if competition exist
    if($competition->cyanide_id != 'NULL'){
      $resultTest = $con->query("SELECT id FROM site_competitions WHERE cyanide_id=".$competition->cyanide_id);
      $test_competition = $resultTest->fetch_row();
    }
    else {
      $test_competition = [0];
    };

    if ( $test_competition[0] == 0 ){
        $competition->game_name = str_replace("'","\'",$competition->game_name);
        //Saving competition
        $sql = "INSERT INTO site_competitions ( cyanide_id, league_name, param_name_format, champion, pool, game_name, season, competition_mode, site_order, site_name, active, started, competition_id_parent, sponsor_id_1, sponsor_id_2, `round`) VALUES (".$competition->cyanide_id.",'".$competition->league_name."','".$competition->format."','".$competition->champion."','".$competition->pool."','".$competition->game_name."',CONCAT('".$competition->season." ', YEAR(DATE_ADD(NOW(), INTERVAL 500 YEAR)) ),'".$competition->competition_mode."',".$competition->site_order.",'".$competition->site_name."','1',NOW(),".$competition->competition_id_parent.",".$competition->sponsor_id_1.",".$competition->sponsor_id_2.",0)";
        //"
        $con->query($sql);
        $competition_id = $con->insert_id;

        if($competition->competition_mode!='Sponsors' && $competition->competition_mode!='ladder'){
            $request = "https://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1&competition=".urlencode($competition->game_name);
            $response = file_get_contents($request);
            $data = json_decode($response);
            $competition->id = $competition_id;
            $competition->matches = $data->upcoming_matches;
            competition_add_matchs($con, $Cyanide_Key, $competition->id, $competition);
            forum_links_add($con, $competition);
        };

        $json = new stdClass;
        $json->type = "success";
        $json->text = "Compétition créée (bordel! 3 e...)";
        echo json_encode($json);

    }
    else {
        $json = new stdClass;
        $json->type = "failure";
        $json->text = "La compétition existe déjà";
        echo json_encode($json);
    }

};

?>
