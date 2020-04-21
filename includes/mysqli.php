<?php
function Database($options = array()) {
/*! Properties */
	$server = "";
	$database = "";
	$user = "";
	$password = "";

	$server = $options["server"] ?: $server;
	$database = $options["database"] ?: $database;
	$user = $options["user"] ?: $user;
	$password = $options["password"] ?: $password;

	$connection = new mysqli($server, $user, $password, $database);
	IF($connection->connect_error) { printf("Mysqli Connect Error: (%i) %s", $connection->connect_errno, $connection->connect_error); return false; }
	else { return $connection; }
	// or use mysqli_connect_error()
}
?>
