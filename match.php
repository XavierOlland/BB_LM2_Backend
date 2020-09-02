
<?php
set_time_limit(0);
//Retrieve match info
function match_fetch($con,$id){
    mysqli_set_charset($con,'utf8');

    $sqlMatch = "SELECT m.id,
          m.cyanide_id,
          m.contest_id,
          m.competition_id,
          a.site_name as competition_name,
          a.season,
          a.active as competition_active,
          m.round,
          DATE_ADD(m.started, INTERVAL 500 YEAR) AS started,
          m.team_id_1,
          m.team_id_2,
          t1.name AS team_1_name,
          t2.name AS team_2_name,
          t1.logo AS team_1_logo,
          t2.logo AS team_2_logo,
          t1.color_1 AS team_1_color_1,
          t1.color_2 AS team_1_color_2,
          t2.color_1 AS team_2_color_1,
          t2.color_2 AS team_2_color_2,
          t1.param_id_race AS team_1_race,
          t2.param_id_race AS team_2_race,
          c1.id AS coach_id_1,
          c2.id AS coach_id_2,
          c1.name AS coach_1_name,
          c2.name AS coach_2_name,
          m.score_1 AS team_1_score,
          m.score_2 AS team_2_score,
          fl.forum_id AS forum,
          m.forum_url
          FROM site_matchs as m
          LEFT JOIN site_competitions as a ON a.id=m.competition_id
          LEFT JOIN site_teams as t1 ON t1.id=m.team_id_1
          LEFT JOIN site_teams as t2 ON t2.id=m.team_id_2
          LEFT JOIN site_coachs as c1 ON c1.cyanide_id=t1.coach_id
          LEFT JOIN site_coachs as c2 ON c2.cyanide_id=t2.coach_id
          LEFT JOIN site_forum_links as fl ON fl.competition_id=m.competition_id AND fl.round=m.round
          WHERE m.id='".$id."' OR m.cyanide_id='".$id."'";
    $resultMatch = $con->query($sqlMatch);
    $match = $resultMatch->fetch_object();

    $request = './../resources/json/matches/'.$match->cyanide_id.'.json';
    $response  = @file_get_contents($request);
    if($response===FALSE){
      $sqlJson = "SELECT * FROM site_matchs WHERE id=".$match->id;
      $resultJson = $con->query($sqlJson);
      $json = $resultJson->fetch_object();
      $response = json_encode($json);
    }

    $match->json = $response;
    $match->stadium = json_decode($response)->teams[0]->stadiumname;

    $sqlForum = "SELECT forum_id, topic_id FROM forum_posts WHERE post_text LIKE '%<s>[match]</s>".$match->id."<e>[/match]</e>%'";
    $resultForum = $con->query($sqlForum);
    $forum = $resultForum->fetch_object();
    $match->forum_url = "https://bbbl.fr/Forum/viewtopic.php?f=".$forum->forum_id."&t=".$forum->topic_id;
    $sqlForumURL = "UPDATE site_matchs SET forum_url='".$match->forum_url."' WHERE id=".$match->id;
    $con->query($sqlForumURL);

    // $match->bets = [];
    // $sqlBets = "SELECT p.match_id, m.score_1, m.score_2, p.team_score_1, p.team_score_2, c.name FROM site_matchs AS m, site_bets AS p, site_coachs AS c WHERE c.id=p.coach_id AND p.match_id=m.id AND match_id=".$id;
    // $resultBets = $con->query($sqlBets);
    //
    // while($dataBets = $resultBets->fetch_assoc()) {
    //     array_push($match->bets, $dataBets);
    // };

    return $match;

};

//Set date
function match_set_date($con, $params){
    mysqli_set_charset($con,'utf8');
    $sqlMatch = "UPDATE site_matchs SET started = str_to_date('".$params->started."','%d/%m/%Y %H:%i') WHERE id=".$params->id;
    $con->query($sqlMatch);
    $con->close();
//Set date
function vue_match_set_date($con, $params){
    mysqli_set_charset($con,'utf8');
    $sqlMatch = "UPDATE site_matchs SET started = CAST('".$params->started."' AS DATETIME) WHERE id=".$params->id;
    $con->query($sqlMatch);
    $con->close();
};

//Save match
function match_save($con, $Cyanide_Key, $params, $reset){

    $request = 'http://web.cyanide-studio.com/ws/bb2/match/?key='.$Cyanide_Key.'&uuid='.$params[0];
    $response  = file_get_contents($request);
    file_put_contents( './../resources/json/matches/'.$params[0].'.json', $response);
    $json = str_replace("\\","\\\\",$response);
    $matchDetails = json_decode($response);

    //Save match
    $sqlMatch = "UPDATE site_matchs SET
      cyanide_id = '".$matchDetails->uuid."',
      started = '".$matchDetails->match->started."',
      score_1 = '".$matchDetails->match->teams[0]->score."',
      nbsupporters_1 = '".$matchDetails->match->teams[0]->nbsupporters."',
      possessionball_1 = '".$matchDetails->match->teams[0]->possessionball."',
      occupationown_1 = '".$matchDetails->match->teams[0]->occupationown."',
      occupationtheir_1 = '".$matchDetails->match->teams[0]->occupationtheir."',
      sustainedcasualties_1 = '".$matchDetails->match->teams[0]->sustainedcasualties."',
      sustainedko_1 = '".$matchDetails->match->teams[0]->sustainedko."',
      sustainedinjuries_1 = '".$matchDetails->match->teams[0]->sustainedinjuries."',
      sustaineddead_1 = '".$matchDetails->match->teams[0]->sustaineddead."',
      score_2 = '".$matchDetails->match->teams[1]->score."',
      nbsupporters_2 = '".$matchDetails->match->teams[1]->nbsupporters."',
      possessionball_2 = '".$matchDetails->match->teams[1]->possessionball."',
      occupationown_2 = '".$matchDetails->match->teams[1]->occupationown."',
      occupationtheir_2 = '".$matchDetails->match->teams[1]->occupationtheir."',
      sustainedcasualties_2 = '".$matchDetails->match->teams[1]->sustainedcasualties."',
      sustainedko_2 = '".$matchDetails->match->teams[1]->sustainedko."',
      sustainedinjuries_2 = '".$matchDetails->match->teams[1]->sustainedinjuries."',
      sustaineddead_2 = '".$matchDetails->match->teams[1]->sustaineddead."'
      WHERE contest_id=".$params[1]." OR cyanide_id = '".$matchDetails->uuid."'";
      $con->query($sqlMatch);
    if($reset == 1){
        $con->query("DELETE FROM site_players_stats WHERE cyanide_id_match='".$matchDetails->uuid."'");
    };

    // Update teams and stats
    foreach ($matchDetails->match->teams as $team){
        // Update team
        $teamBBBL = team_update($con, $Cyanide_Key, $team->idteamlisting);
        // Add players stats
        foreach ($team->roster as $player) {
            if($player->id){
                player_stats_save($con, $player, $matchDetails->uuid);
            }
        };
    };

    // Post match link to Discord
    $match = match_fetch($con, $matchDetails->uuid);
    match_to_discord('#FFCC00', $match );
};

function save_all_to_json($con, $Cyanide_Key){
    $sql = "SELECT cyanide_id FROM site_matchs WHERE cyanide_id IS NOT NULL AND competition_id>231";
    $result = $con->query($sql);
    while($data = $result->fetch_object()) {
        $request = 'http://web.cyanide-studio.com/ws/bb2/match/?key='.$Cyanide_Key.'&uuid='.$data->cyanide_id;
        $response  = file_get_contents($request);
        file_put_contents( './../resources/json/matches/'.$data->cyanide_id.'.json', $response);
    }
};


?>
