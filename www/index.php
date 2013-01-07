<?php
$f3=require('../lib/base.php');
$f3->config('../app/app.cfg');
$f3->config('../app/routes.cfg');


/* DB params */
$f3->set('authlogin','admin');
$f3->set('authpwd','admin');
$f3->set('dburi','mongodb://127.0.0.1:27017');
$f3->set('dbname','blog');


/*Enable to insert this route in config file atm. */
$f3->route('GET /minify/@type',	function() use($f3) {
		echo Web::instance()->minify($_GET['files']);
	},6400
);

$f3->run();
?>