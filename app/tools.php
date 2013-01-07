<?php

class tools {

	public static function checkPassword() {
		$f3 = \Base::instance();
		$pwd = $f3->get('POST.pwd');
		$f3->scrub($pwd);

		if (!$f3->exists('SESSION.messages.general.error')) {
			if (empty($pwd))
				$f3->push('SESSION.messages.general.error', 'Password must be specified');
			elseif (strlen($pwd) > 24)
				$f3->push('SESSION.messages.general.error', 'Invalid password');
		}
	}

	public static function checkID() {
		$f3 = \Base::instance();
		$id = $f3->get('POST.id');
		$f3->scrub($id);

		if (!$f3->exists('SESSION.messages.general.error')) {
			if (empty($id))
				$f3->push('SESSION.messages.general.error', 'Username should not be blank');
			elseif (strlen($id) > 24)
				$f3->push('SESSION.messages.general.error', 'Username is too long');
			elseif (strlen($id) < 3)
				$f3->push('SESSION.messages.general.error', 'Username is too short');
		}
		$_POST['id'] = strtolower($id);
	}

	public static function summarize($string, $numwords = '5', $etc = '...') {
		$tmp = explode(" ", strip_tags($string));
		$stringarray = array();
		for ($i = 0; $i < count($tmp); $i++) {
			if ($tmp[$i] != '')
				$stringarray[] = $tmp[$i];
		}
		if ($numwords >= count($stringarray)) {
			return $string;
		}
		$tmp = array_slice($stringarray, 0, $numwords);
		return implode(' ', $tmp) . $etc;
	}

	public static function slug($str, $maxlen = 255) {
		$slug = \Base::instance()->scrub($str);
		$slug = strtolower($str);
		$slug = preg_replace("/[^a-z0-9\s-]/", "", $slug);
		$slug = trim(preg_replace("/[\s-]+/", " ", $slug));
		return $slug = preg_replace("/\s/", "-", $slug);
	}

	public static function intMe($str) {
		return (int)abs($str);
	}

}
