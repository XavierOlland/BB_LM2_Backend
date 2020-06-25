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
    FROM site_competitions AS c  WHERE c.id = ".$id;
    $result = $con->query($sql);
    $competition = $result->fetch_object();

    if($competition->competition_mode!='Sponsors'){
        $competition->standing = competition_standing($con, $id);
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
function competition_standing($con, $id){
    $standing = [];
    $sqlStanding = 'SELECT
          id,
          cyanide_id,
          logo,
          race,
          name,
          color_1,
          color_2,
          coach,
          COUNT(case when score_1 > score_2 then 1 end) AS V,
          COUNT(case when score_1 = score_2 then 1 end) AS N,
          COUNT(case when score_2 > score_1 then 1 end) AS D,
          SUM(score_1) AS TDfor,
          SUM(score_1) - SUM(score_2) AS TD,
          SUM(sustainedcasualties_2 ) - SUM(sustainedcasualties_1) AS S,
          SUM( case when score_1 > score_2 then 3 else 0 end
              + case when score_1 = score_2 AND score_1 IS NOT NULL then 1 else 0 end
          ) AS Pts,
          SUM(score_1) - SUM(score_2) + SUM(sustainedcasualties_2 ) - SUM(sustainedcasualties_1) AS TDS,
          0 AS confrontation1,
          0 AS confrontation2
          FROM (
          SELECT site_matchs.id AS m, site_teams.id AS id, site_teams.cyanide_id AS cyanide_id, site_teams.logo AS logo, site_teams.param_id_race AS race, site_teams.name AS name, site_teams.color_1 AS color_1, site_teams.color_2 AS color_2, site_coachs.name AS coach, score_1, score_2, sustainedcasualties_1, sustainedcasualties_2, sustaineddead_1, sustaineddead_2 FROM site_matchs
          LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_1
          INNER JOIN site_coachs ON site_coachs.cyanide_id=site_teams.coach_id
          WHERE competition_id = '.$id.'
          UNION
          SELECT site_matchs.id AS m, site_teams.id AS id, site_teams.cyanide_id AS cyanide_id, site_teams.logo AS logo, site_teams.param_id_race AS race, site_teams.name AS name, site_teams.color_1 AS color_1, site_teams.color_2 AS color_2, site_coachs.name AS coach, score_2, score_1, sustainedcasualties_2, sustainedcasualties_1, sustaineddead_2, sustaineddead_1 FROM site_matchs
          LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_2
          INNER JOIN site_coachs ON site_coachs.cyanide_id=site_teams.coach_id
          WHERE competition_id='.$id.'
          ) AS a
          WHERE LENGTH(coach)>0
          GROUP BY id
          ORDER BY Pts DESC, V DESC, TDS DESC';

    $resultStanding = $con->query($sqlStanding);
    while($dataStanding = $resultStanding->fetch_assoc()) {
        array_push($standing, $dataStanding);
    }

    //Managing exaequo
    for($j = 1; $j <= 2; $j++) {
      $limit = count($standing);

      for($i = 1; $i <= $limit-1; $i++) {
          $row = [1];
          if ( $standing[$i]['Pts'] == $standing[$i-1]['Pts'] ) {
              $sqlConfrontation = 'SELECT
              case when score_1 > score_2 then 2 else
              case when score_1 = score_2 AND score_1 IS NOT NULL then 1
              else 0 end end,
              name
              FROM (
              SELECT site_teams.id, site_teams.name, score_1, score_2 FROM site_matchs
              LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_1
              WHERE competition_id = '.$id.' AND site_matchs.team_id_1 = '.$standing[$i]['id'].' AND site_matchs.team_id_2 = '.$standing[$i-1]['id'].'
              UNION
              SELECT site_teams.id, site_teams.name, score_2, score_1 FROM site_matchs
              LEFT JOIN site_teams ON site_teams.id=site_matchs.team_id_2
              WHERE competition_id='.$id.' AND site_matchs.team_id_2 = '.$standing[$i]['id'].' AND site_matchs.team_id_1 = '.$standing[$i-1]['id'].'
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
        $standing[$i]['confrontation'.$j] = $row[0];
      }

      array_multisort(array_column($standing, 'Pts'),  SORT_DESC,
                array_column($standing, 'confrontation1'), SORT_DESC,
                array_column($standing, 'confrontation2'), SORT_DESC,
                array_column($standing, 'V'), SORT_DESC,
                array_column($standing, 'TDS'), SORT_DESC,
                $standing);
    }
    return $standing;

};

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
  if($params[2] == 'ladder'){
      competition_ladder_update($con, $Cyanide_Key, $Cyanide_League, $params);
  };
  // else {
  //   if(count($params[4]) != 0){
  //       competition_update_matchs($con, $Cyanide_Key, $Cyanide_League, $params);
  //   }
  //   elseif (in_array($params[2],['swiss','single_elimination'])) {
  //       competition_next_round($con, $Cyanide_Key, $Cyanide_League, $params);
  //   }
  // }
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

    $request = "http://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&competition=".urlencode($params[0])."&status=played&round=".$params[3];
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
    $request = "http://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&competition=".urlencode($params[0])."&exact=1&status=scheduled&round=".$nextRound;
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
            VALUES (".$match->contest_id.",IFNULL('".$match->uuid."',NULL),".$competitionID.",".$match->round.",".$match->teamBBBL[0].",".$match->teamBBBL[1].")";
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
  $request = "http://web.cyanide-studio.com/ws/bb2/matches/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1&competition=".urlencode($params[0])."&start=".urlencode($DateLastgame[0]);
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
  $request = "http://web.cyanide-studio.com/ws/bb2/competitions/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1";
  $response = file_get_contents($request);
  $arr = json_decode($response);
  $competitions = array_filter($arr->competitions, function($item){
    if($item->status==1)
      {
          return true;
      }
  });
  echo json_encode(array_values($competitions));
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
        //Saving competition
        $sql = "INSERT INTO site_competitions ( cyanide_id, league_name, param_name_format, champion, pool, game_name, season, competition_mode, site_order, site_name, active, started, competition_id_parent, sponsor_id_1, sponsor_id_2, `round`) VALUES (".$competition->cyanide_id.",'".$competition->league_name."','".$competition->format."','".$competition->champion."','".$competition->pool."','".$competition->game_name."',CONCAT('".$competition->season." ', YEAR(DATE_ADD(NOW(), INTERVAL 500 YEAR)) ),'".$competition->competition_mode."',".$competition->site_order.",'".$competition->site_name."','1',NOW(),".$competition->competition_id_parent.",".$competition->sponsor_id_1.",".$competition->sponsor_id_2.",0)";
        //"
        $con->query($sql);
        $competition_id = $con->insert_id;

        if($competition->competition_mode!='Sponsors' && $competition->competition_mode!='ladder'){
            $request = "http://web.cyanide-studio.com/ws/bb2/contests/?key=".$Cyanide_Key."&league=".urlencode($Cyanide_League)."&exact=1&competition=".urlencode($competition->game_name);
            $response = file_get_contents($request);
            $data = json_decode($response);
            $competition->id = $competition_id;
            $competition->matches = $data->upcoming_matches;
            competition_add_matchs($con, $Cyanide_Key, $competition->id, $competition);
            //forum_links_add($con, $competition);
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
