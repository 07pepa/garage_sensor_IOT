<?php
use Slim\Http\Request;
use Slim\Http\Response;
class Problem Extends Exception{
	public function __construct($message, $code = 500, Exception $previous = null) {
    parent::__construct($message, $code, $previous);
    }
}

function Rpilogincheck(&$login, &$heslo, &$db){
	$stmt=$db->prepare("SELECT id_malina, login, heslo FROM maliny WHERE login=:log");
	$stmt->bindValue(":log",$login);
	$stmt->execute();
	$rpi=$stmt->fetch();
	$out= array();
	$out["valid"]=$rpi&&password_verify($heslo, $rpi["heslo"]);
	$out["id_malina"]=$rpi["id_malina"];
	return $out;
}

function userlogincheck(&$login, &$heslo, &$db){
	$stmt=$db->prepare("SELECT id_uzivatel, heslo FROM uzivatele WHERE user_login=:log");
	$stmt->bindValue(":log", $login);
	$stmt->execute();
	$usr=$stmt->fetch();
	$out= array();
	$out["valid"]=$usr&&password_verify($heslo, $usr["heslo"]);
	$out["id_uzivatel"]=$usr["id_uzivatel"];
	return $out;
}

function all_Keys_Exist(array $keys, array &$in_array){
	 foreach( $keys as &$val){
		 if(!array_key_exists($val, $in_array)){
			 return false;
		 }
	 }
	return true;
}

function idRpiValid(&$id, &$db){
	$stmt= $db->prepare("SELECT COUNT(id_malina)=1 AS cnt FROM maliny WHERE id_malina= :id");
	$stmt->bindValue(":id",$id);
	$stmt->execute();
	$out=$stmt->fetch();
	return $out["cnt"];
}

function jeToMojeMalina(&$iduser, &$idmalina, &$db){
	$stmt= $db->prepare("SELECT COUNT(id_malina)=1 AS cnt FROM maliny WHERE id_malina= :id AND id_uzivatel= :usr");
	$stmt->bindValue(":id",$idmalina);
	$stmt->bindValue(":usr",$iduser);
	$stmt->execute();
	$out=$stmt->fetch();
	return $out["cnt"];
}

function idGarazExists(&$garazID, &$idMalina, $db){
	$stmt= $db->prepare("SELECT COUNT(id_garaz)=1 AS cnt from garaze WHERE id_garaz=:gar AND id_malina=:malina");
	$stmt->bindValue(":gar", $garazID);
	$stmt->bindValue(":malina", $idMalina);
	$stmt->execute();
	$out=$stmt->fetch();
	return $out["cnt"];
}

function jeMojegaraz(&$garazID, &$iduser, $db){
	$stmt= $db->prepare("SELECT COUNT(id_garaz)=1 AS cnt from garaze JOIN maliny USING(id_malina) JOIN uzivatele USING(id_uzivatel) WHERE id_garaz=:gar AND id_uzivatel=:usr");
	$stmt->bindValue(":gar", $garazID);
	$stmt->bindValue(":usr", $iduser);
	$stmt->execute();
	$out=$stmt->fetch();
	return $out["cnt"];
}

function idStavGarazValid(&$id, &$db){
	$stmt= $db->prepare("SELECT COUNT(id_stav)=1 AS cnt FROM garaze_stav WHERE id_stav= :id");
	$stmt->bindValue(":id", $id);
	$stmt->execute();
	$out=$stmt->fetch();
	return $out["cnt"];
}

function garazPoslStav(&$id_garaz, &$id_malina, &$db){
	$stmt=$db->prepare("SELECT  gar.id_garaz AS id,stav.id_stav
  FROM (SELECT id_garaz FROM garaze  WHERE id_malina=:id and id_garaz=:id_gar)AS gar JOIN (SELECT id_garaz, id_stav,cas FROM   garaze_historie ORDER  BY cas DESC NULLS LAST )AS stav USING (id_garaz)  LIMIT(1)");
	$stmt->bindValue(":id_gar", $id_garaz);
	$stmt->bindValue(":id", $id_malina);
	$stmt->execute();
	return $stmt->fetch();
}

function updateLast_Seen(&$raspberryID, &$db){
	$heartbeat=$db->prepare("UPDATE maliny SET last_seen=date_trunc('second', localtimestamp) WHERE id_malina= :id");
	$heartbeat->bindValue(":id", $raspberryID); 
	$heartbeat->execute();
}