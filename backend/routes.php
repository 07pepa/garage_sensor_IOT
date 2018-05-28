<?php
use Slim\Http\Request;
use Slim\Http\Response;
// Routes
include("Helper.php");
$app->get('/', function (Request $request, Response $response, array $args){
	$hilfe["foo"]="This is API for IoT - humans are not welcomed";
	return $this->view->render($response, 'index.latte', $hilfe);
});

$app->post('/heartbeat', function (Request $request, Response $response, array $args) {
	$data=$request->getParsedBody();
	try {
		$check=Rpilogincheck($data["login"],$data["heslo"],$this->db);
		if(!$check["valid"])
			throw new Problem("Neautorizovano");
		updateLast_Seen($check["id_malina"],$this->db);
		$stmt= $this->db->prepare("SELECT limit_min, limit_max, id_garaz AS id FROM garaze WHERE id_malina= :id");
		$stmt->bindValue(":id",$check["id_malina"]); 
		$stmt->execute();
		$garaze=$stmt->fetchAll();
		return $response->withJson(["garaze" =>$garaze],200);
	} catch (Exception $vyjimka) {
		$code=($vyjimka instanceof Problem)?401:500;
		return $response->withJson(["msg" =>$vyjimka->getMessage()],$code);
	}
});

$app->get('/garaze', function (Request $request, Response $response, array $args){
	$ingress=$request->getQueryParams();
	try {
		if($ingress==null || !all_Keys_Exist(array("login","heslo"), $ingress))
			throw new Problem("neznamy login a heslo", 401);
		$check=Rpilogincheck($ingress["login"], $ingress["heslo"], $this->db);
		if(!$check["valid"])
			throw new Problem("Neautorizovano", 401);
		if(array_key_exists ("id",$ingress)){
			if($ingress["id"]=="")
				throw new Problem("atribut id neni cislo", 400);
			$garaz=garazPoslStav($ingress["id"], $check["id_malina"], $this->db);
			if($garaz){ 
				return $response->withJson(["garaze" =>$garaz], 200);
			}
			else{ 
				return $response->withStatus(204);
			}
		}
		$stmt=$this->db->prepare("SELECT id_garaz AS id FROM garaze WHERE id_malina=:id");
		$stmt->bindValue(":id",$check["id_malina"]); 
		$stmt->execute();
		$vseckaid=$stmt->fetchAll();
		if(!$vseckaid)
			return $response->withStatus(204);
		$garaze=array();
		foreach ($vseckaid as &$jedno){
			array_push($garaze,garazPoslStav($jedno["id"], $check["id_malina"], $this->db));
		}
		return $response->withJson(["garaze" =>$garaze], 200);
	} catch (Exception $vyjimka) {
		$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
		return $response->withJson(["msg" =>$vyjimka->getMessage()], $code);
	}
});

$app->post('/garaze', function (Request $request, Response $response, array $args){
	$ingress=$request->getQueryParams();
	$transakce=false;
	try {
		if($ingress==null || !all_Keys_Exist(array("login", "heslo"), $ingress))
			throw new Problem("neznamy login a heslo", 401);
		$check=userlogincheck($ingress["login"], $ingress["heslo"], $this->db);
		if(!$check["valid"])
			throw new Problem("Neautorizovano", 401);
		$data=$request->getParsedBody();
		if(!all_Keys_Exist(array("poznamka","limit_min","limit_max","id_malina"),$data))
			throw new Problem("JSON neobsahuje všechny klíče", 400);
		if (!jeToMojeMalina($check["id_uzivatel"], $data["id_malina"], $this->db))
			throw new Problem("Tahle malina ti nepatří nebo neexistuje", 400);
		$this->db->beginTransaction();
		$transakce=true;
		$stmt=$this->db->prepare("INSERT INTO garaze(poznamka,limit_min,limit_max,id_malina) VALUES(:pozn,:min,:max,:id)");
		$stmt->bindValue(":id", $data["id_malina"]); 
		$stmt->bindValue(":pozn", $data["poznamka"]);
		$stmt->bindValue(":min", $data["limit_min"]);
		$stmt->bindValue(":max", $data["limit_max"]); 
		$stmt->execute();
		$stmt=$this->db->prepare("INSERT INTO garaze_historie(id_stav,id_garaz,cas) VALUES(1,:id,date_trunc('second', localtimestamp))");
		$stmt->bindValue(":id",$this->db->lastInsertId("garaze_id_garaz_seq"));
		$stmt->execute();
		$this->db->commit();
		return $response->withStatus(201);
	} catch (Exception $vyjimka) {
		if ($transakce){
			$this->db->rollback();
		}
		$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
		return $response->withJson(["msg" =>$vyjimka->getMessage()],$code);
	}
});

$app->post('/garaze/update', function (Request $request, Response $response, array $args){
	$ingress=$request->getQueryParams();
	$udelanegaraze=array();
	$garazeInbound=array();
	try {
		if($ingress==null || !all_Keys_Exist(array("login","heslo"),$ingress))
			throw new Problem("neznamy login a heslo", 401);
		$check=Rpilogincheck($ingress["login"],$ingress["heslo"], $this->db);
		if(!$check["valid"])
			throw new Problem("Neautorizovano", 401);
		updateLast_Seen($check["id_malina"],$this->db);
		$garaze=$request->getParsedBody();
		if(!isset($garaze["garaze"]))
			throw new Problem("garaze nejsou nastaveny", 400);
		$garaze=$garaze["garaze"];
		$garazeInbound=$garaze;
		$stmt=$this->db->prepare("SELECT id_stav AS stav FROM garaze_stav");
		$stmt->execute();
		$stv=$stmt->fetchAll();
		$stavy= array();
		foreach($stv as &$uno) array_push($stavy,$uno["stav"]);
		$idOK=array();
			$querry="INSERT INTO garaze_historie (id_garaz,id_stav,cas) VALUES(:id ,:stav ,";
		foreach ($garaze as &$garaz){
			if(!isset($garaz["stav"],$garaz["id"]))
				throw new Problem("chybí id nebo stav", 400);
			if(!in_array($garaz["stav"],$stavy))
				throw new Problem("stav nepovolen", 400);
			if(!in_array($garaz["id"],$idOK)){
				if(idGarazExists($garaz["id"],$check["id_malina"],$this->db))
					array_push($idOK,$garaz["id"]);
				else
					throw new Problem("id garaze je velky spatny", 400);
			}
			$zbytek=(isset($garaz["cas"]))?" to_timestamp(:T, 'dd-mm-yyyy hh24:mi:ss'))":" date_trunc('second', localtimestamp))";
			$stmt=$this->db->prepare($querry.$zbytek);
			if (isset($garaz["cas"])) $stmt->bindValue(":T",$garaz["cas"]);
			$stmt->bindValue(":id",intval($garaz["id"]));
			$stmt->bindValue(":stav",(intval($garaz["stav"])));
			$stmt->execute();
			array_push($udelanegaraze, $garaz);
		}
		return $response->withStatus(201);
	} catch (Exception $vyjimka) {
		$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
	if(count($garazeInbound)==count($udelanegaraze)){
	return $response->withJson(["msg" =>$vyjimka->getMessage()], $code);}
	else 
		error_reporting(0);
		$out=array_diff($garazeInbound, $udelanegaraze);
		return $response->withJson(["msg" =>$vyjimka->getMessage(), "undone"=>$out], $code);
	}
});

$app->post('/frontend/updategaraz', function (Request $request, Response $response, array $args){
	$json=$request->getParsedBody();
	$needRollBack=false;
	try {
		if(!isset($json)) throw new Problem("JSON is probably deformed",400);
		if(!isset($json["login"],$json["heslo"],$json["limit_min"],$json["limit_max"],$json["id_garaze"]))
			throw new Problem("nejsou nastaveny vsechny polozky JSONU",400);
	
		$check=userlogincheck($json["login"],$json["heslo"],$this->db);
		if(!$check["valid"])
			throw new Problem("Neautorizovano",401);
		if(!jeMojegaraz($check["id_uzivatel"],$json["id_garaze"],$this->db))
			throw new Problem("Toto není vaše garaz nebo id neexistuje",400);
		$malinaExist=false;

		if(isset($json["new_id_malina"])){
			if (!jeToMojeMalina($check["id_uzivatel"],$data["new_id_malina"],$this->db))
				throw new Problem("Tahle malina ti nepatří nebo neexistuje",400);
		$malinaExist=true;
		}
					
		$this->db->beginTransaction();
		$needRollBack=true;
		$stmt=$this->db->prepare("UPDATE garaze SET limit_min= :min,limit_max=:max WHERE id_garaz=:id");
		$stmt->bindValue(":min", $json["limit_min"]);
		$stmt->bindValue(":max", $json["limit_max"]);
		$stmt->bindValue(":id", intval($json["id_garaze"]));
		$stmt->execute();
		if($malinaExist){
		$stmt=$this->db->prepare("UPDATE garaze SET id_malina= :newid WHERE id_garaz=:id");
		$stmt->bindValue(":newid", $json["new_id_malina"]);
		$stmt->bindValue(":id", intval($json["id_garaze"]));
		$stmt->execute();
		}
		if(isset($json["poznamka"])){
		$stmt=$this->db->prepare("UPDATE garaze SET poznamka= :new WHERE id_garaz=:id");
		$stmt->bindValue(":new", $json["poznamka"]);
		$stmt->bindValue(":id", intval($json["id_garaze"]));
		$stmt->execute();
		}
		$this->db->commit();
		return $response->withStatus(200);
	} catch (Exception $vyjimka) {
	$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
	if($needRollBack)
		$this->db->rollback();
	return $response->withJson(["msg" =>$vyjimka->getMessage()],$code);
	}
});

$app->get('/frontend/getAll', function (Request $request, Response $response, array $args){
	$id=$request->getQueryParams();
	try {
		if(!isset($id["idRpi"])||!idRpiValid($id["idRpi"],$this->db))
			throw new Problem("id RPI je velky spatny",400);
		$stmt=$this->db->prepare('SELECT to_char(case WHEN last_seen>garlast THEN last_seen ELSE garlast END,\'YYYY-MM-DD"T"HH24:MI:SS\') AS last_update FROM maliny JOIN (SELECT MAX(cas)garlast, CAST(:id AS INT) AS id_malina FROM garaze_historie JOIN garaze USING(id_garaz) WHERE id_malina=:id) foo USING (id_malina)');
		$stmt->bindValue(":id", intval($id["idRpi"]));
		$stmt->execute();
		$last_seen=$stmt->fetch();
		$last_seen=$last_seen["last_update"];
		$stmt=$this->db->prepare("SELECT id_garaz AS id FROM garaze  WHERE id_malina=:id");
		$stmt->bindValue(":id", $id["idRpi"]); 
		$stmt->execute();
		$vseckaid=$stmt->fetchAll();
		if(!$vseckaid)
			return $response->withStatus(204);
		$garaze=array();
		foreach ($vseckaid as &$jedno){
			$stmt=$this->db->prepare("SELECT  gar.id_garaz AS id,stav.id_stav,to_char(cas,'YYYY-MM-DD\"T\"HH24:MI:SS') AS last_update
            FROM (SELECT id_garaz FROM garaze  WHERE id_malina=:id and id_garaz=:id_gar)AS gar JOIN (SELECT id_garaz, id_stav,cas FROM   garaze_historie ORDER  BY cas DESC NULLS LAST )AS stav USING (id_garaz)  LIMIT(1)");
			$stmt->bindValue(":id_gar", $jedno["id"]);
			$stmt->bindValue(":id", $id["idRpi"]);
			$stmt->execute();
			$pom=$stmt->fetch();
		array_push($garaze, $pom);
			}
		return $response->withJson(["rpi_last_seen" =>$last_seen, "garaze"=>$garaze], 200);
	} catch (Exception $vyjimka) {
		$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
		return $response->withJson(["msg" =>$vyjimka], $code);
	}
});


$app->get('/frontend/getAllwithHistory', function (Request $request, Response $response, array $args){
	$id=$request->getQueryParams();
	try {
		if(!isset($id["idRpi"])||!idRpiValid($id["idRpi"],$this->db))
			throw new Problem("id RPI je velky spatny",400);
		$stmt=$this->db->prepare("SELECT id,id_stav,last_update FROM (SELECT  id_garaz AS id,id_stav,to_char(cas,'YYYY-MM-DD\"T\"HH24:MI:SS') AS last_update,cas FROM garaze_historie JOIN garaze USING(id_garaz) JOIN maliny USING (id_malina) WHERE id_malina=:id)foo ORDER BY id,cas DESC");
		$stmt->bindValue(":id", $id["idRpi"]);
		$stmt->execute();
		$garaze=$stmt->fetchAll();
		return $response->withJson(["garaze"=>$garaze], 200);
	} catch (Exception $vyjimka) {
		$code=($vyjimka instanceof Problem)?$vyjimka->getCode():500;
		return $response->withJson(["msg" =>$vyjimka], $code);
	}
});