<?
# *******************************************************************
# class.hodinky.php-------------------------------------WebWorks.cz--
# ------------------------- trida pro praci s databazi --------------
# 
# *******************************************************************

class Database {
	var $user, $password, $database, $server, $result, $db;
	var $messages = array (); # debug informace

	# ...................................................................
	# KONSTRUKTOR
	# bool Database ( string server, string database, string user, string pass )
	function Database ($server, $database, $user, $password) {
		# kontrola predanych hodnot
		if (!$server || !$database || !$user)
			return (false);

		$this->server = $server;
		$this->database = $database;
		$this->user = $user;
		$this->password = $password;

		return (true);
		}
		
	# ...................................................................
	# bool _connect ( )
	# pripoji se k databazi
	function _connect () {
		# pripojeni MySQL databaze
		if ($this->db = mysql_pconnect ($this->server, $this->user, $this->password)) {
			if (!mysql_select_db ($this->database, $this->db))	{
				$this->messages['system'] = 'DB connected but wrong database name "' . $this->database . '"!';
				return (false);
				}
			}
		else {
			$this->messages['system'] = 'DB not connected!';
			return (false);
			}
		$this->messages['system'] = 'DB connected.';

		$this->messages['total_time'] = 0;
		$this->messages['total_queries'] = 0;

		return ($this->db);
		}
	
	# ...................................................................
	# resource query ( string query [. string query_name)
	# pozadavek na databazi
	# dopsat moznost INSERT, DELETE a UPDATE
	function query ($query, $query_name = '') {
		unset ($this->result);

		# pokud neni pripojena databaze, pripoj
		if (!$this->db)
			$this->_connect ();

		if ($this->db) {
			# spusteni mereni
			list($usec, $sec) = explode(" ",microtime());
			$time_start = (float) $usec + (float) $sec;

			# vlastni dotaz
			$this->result = mysql_query($query, $this->db);

			#konec mereni a zapsani do $messages
			list($usec, $sec) = explode(" ",microtime());
			$elapsed = round (((float) $usec + (float) $sec) - $time_start, 5);
			$this->messages['total_time'] += $elapsed;
			$this->messages['total_queries']++;
			if ($query_name && $query_name != '') {
				$this->messages['queries_summary'][$query_name]++;
				$this->messages['queries'][] = array ('name' => $query_name, 'time' => (string) $elapsed, 'query' => $query);				
				}
			else {
				$this->messages['queries_summary']['undefined']++;
				$this->messages['queries'][] = array ('time' => (string) $elapsed, 'query' => $query);
				}
			
			}
		return ($this->result);
		}

	# ...................................................................
	# int numRows (  )
	# vrati pocet radek dotazu
	# dopsat moznost INSERT, DELETE a UPDATE
	function numRows () {
		if (!$this->db)
			return (false);
		
		$output = @mysql_num_rows ($this->result);
		if ($output)
			return ($output);
		else
			return (mysql_affected_rows ());
		}

	# ...................................................................
	# mixed getRow ( [resource result] )
	# vrati radku (jako asociativni pole) vysledku query (posledniho nebo predaneho) dotazu
	# dopsat moznost INSERT, DELETE a UPDATE
	function getRow ($result = null) {
		if (!$this->db)
			return (false);
		
		if ($result)
			$radka = mysql_fetch_assoc ($result);
		else
			$radka = mysql_fetch_assoc ($this->result);
		
		if (is_array ($radka))
			return ($radka);
		else 
			return (false);
		}
	
	# ...................................................................
	# array getAllRows ( [resource result] [, string index] )
	# vrati array array - kompletni vysledek z DB
	# dopsat moznost INSERT, DELETE a UPDATE - blbost?
	function getAllRows ($result = null, $indexby = null) {
		if (!$this->db)
			return (false);
		
		while ($row = $this->getRow ($result)) {
			if ($indexby)
				$output[$row[$indexby]] = $row;
			else
				$output[] = $row;
			}
		return ($output);
		}

	# ...................................................................
	# string getResult ( [resource result] )
	# vrati 1 sloupec prvni radky vysledku, hodi se pro jednoduche dotazy
	# dopsat moznost INSERT, DELETE a UPDATE - blbost?
	function getResult ($result = null) {
		if (!$this->db)
			return (false);
		
		if ($result)
			return (mysql_result ($result, 0, 0));
		else
			return (mysql_result ($this->result, 0, 0));
		}
	
	# ...................................................................
	# string getRowsCount ( [resource result] )
	# vrati celkovy pocet vysledku, kdyby nebylo pouzito LIMIT (musi byt predtim specialni volaniSELECT SQL_...)
	function getRowsCount () {
		if (!$this->db)
			return (false);
		return ($this->getResult ($this->query ('SELECT FOUND_ROWS();', 'FOUND ROWS')));
		}
	
	# ...................................................................
	# string getNumAffected ( [resource result] )
	# vrati celkovy pocet radku, ktere byly ovlivneny
	function getNumAffected () {
		if (!$this->db)
			return (false);
		return (mysql_affected_rows ());
		}

	# ...................................................................
	# string getId ( )
	# vrati ID posledni pridane polozky
	# dopsat moznost INSERT, DELETE a UPDATE - blbost?
	function getId () {
		if (!$this->db)
			return (false);
		
		return (mysql_insert_id ($this->db));
		}
	
	# ...................................................................
	function freeResult ($result = null) {
		if (!$this->db)
			return (false);
		
		if ($result)
			return (mysql_free_result ($result));
		else
			return (mysql_free_result ($this->result));
		}
	
	}
?>