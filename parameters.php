<?php
/**
 * Get the parameters and game definitions & translations
 * @param $con la connexion à la BDD
 */
function parameters($con){
  $sql_parameters = "SELECT type, name, translation FROM site_parameters";
  $res_parameters = $con->query($sql_parameters);
  while($data = $res_parameters->fetch_object()) {
    $parameters[] = $data;
	}
  return $parameters;
};

/**
 * Get the upcoming games planned by players
 * @param $con la connexion à la BDD
 */
function upcomingGames($con){
  $fixtures = [];
  $sqlCompetitions = "SELECT DISTINCT(DATE_FORMAT(m.started,'%Y-%m-%d')) AS day
    FROM site_matchs AS m
    LEFT JOIN site_competitions AS c ON c.id=m.competition_id
    WHERE c.active=1 AND m.cyanide_id IS NULL AND m.started>NOW()
    ORDER BY m.started";
  $resultCompetitions = $con->query($sqlCompetitions);
  while($competitions = $resultCompetitions->fetch_object()){
    $competitions->matchs = [];
    $sqlMatchs = "SELECT m.id, DATE_FORMAT(m.started,'%Y%m%dT%H%i%sZ') AS planned,
      c.league_name, m.round,
      t1.name as name_1, t1.logo as logo_1,
      t2.name as name_2, t2.logo as logo_2
      FROM site_matchs AS m
      LEFT JOIN site_competitions AS c ON c.id=m.competition_id
      LEFT JOIN site_teams AS t1 ON t1.id=m.team_id_1
      LEFT JOIN site_teams AS t2 ON t2.id=m.team_id_2
      WHERE m.cyanide_id IS NULL AND m.started IS NOT NULL AND DATE_FORMAT(m.started,'%Y-%m-%d')='".$competitions->day."'
      ORDER BY c.site_order, planned ASC";
    $resultMatchs = $con->query($sqlMatchs);
    while($dataMatchs = $resultMatchs->fetch_object()){
      array_push($competitions->matchs, $dataMatchs);
    }
    array_push($fixtures, $competitions);
  }
  echo json_encode($fixtures,JSON_NUMERIC_CHECK);
}
/**
 * Get the last games played
 * @param $con la connexion à la BDD
 */
function lastGames($con){
  $fixtures = [];
  $sqlMatchs = "SELECT m.id, DATE_FORMAT(m.started,'%Y%m%dT%H%i%sZ') AS played,
    c.league_name, m.round,
    t1.name as name_1, t1.logo as logo_1, m.score_1,
    t2.name as name_2, t2.logo as logo_2, m.score_2
    FROM site_matchs AS m
    LEFT JOIN site_competitions AS c ON c.id=m.competition_id
    LEFT JOIN site_teams AS t1 ON t1.id=m.team_id_1
    LEFT JOIN site_teams AS t2 ON t2.id=m.team_id_2
    WHERE m.cyanide_id IS NOT NULL AND c.active=1 ORDER BY m.started DESC LIMIT 10";
  $resultMatchs = $con->query($sqlMatchs);
  while($dataMatchs = $resultMatchs->fetch_object()){
    array_push($fixtures, $dataMatchs);
  }
  echo json_encode($fixtures,JSON_NUMERIC_CHECK);
}
?>
