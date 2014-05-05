<?

class Modul {
	var $cache; # cache soubor
	var $DB; # globalni objekt pro praci s databazi
	var $cachetotal; # cache s pocet celkovych radek odpovidajicich poslednimu getu
	var $cachesql; # cache sql query posledniho getu
	
	var $sqlbase; # zaklad SQL dotazu
	var $sqlupdate; # zaklad SQL dotazu - UPDATE
	var $sqlinsert; # zaklad SQL dotazu - INSERT
	var $sqltable;
	var $idformat = 'id';
	var $sqlgrouptotal;
	var $order = -6;
	var $limit = 20;
	var $sync = array ();
	var $fulltextcolumns; # zakladni sloupce pro hledani
		
	# ...................................................................
	# KONSTRUKTOR
	function Modul (& $database) {
		$this->DB = & $database; # globalni objekt pro praci s databazi
		$this->cache = array ();

		if (!is_object ($this->DB))
			return (false);
		return (true);
		}

	# ...................................................................
	function getLimit () {
		return ($this->limit);
		}
	
	# ...................................................................
	function setLimit ($limit = null) {
		if (is_numeric ($limit) && $limit > 0)
			$this->limit = (int) $limit;
		return ($this->limit);
		}
	
	# ...................................................................
	function getNoCalcRows ($where = null, $order = null, $limit = null, $limit_from = null) {
		return ($this->get ($where, $order, $limit, $limit_from, true));
	}
	# ...................................................................
	function get ($where = null, $order = null, $limit = null, $limit_from = null, $nocalcrows = false) {
		if (!$this->sqlbase)
			return (false);

		# zaklad sql dotazu
		$sql = $this->sqlbase;

		# pokud je v sqlbase obsazen GROUP BY, musim to rozdel (mozna i neco dalsiho)
		if (function_exists("strripos")) {
			$posgroupby = strripos ($sql, 'GROUP BY ');
			$lastfrom = strripos ($sql, 'FROM ');
			if ($posgroupby>$lastfrom) {				
				$sql_groupby = substr ($sql,$posgroupby);
				$sql = substr ($sql, 0, $posgroupby);
				}
			}
		elseif (strpos (strtolower ($sql), 'group by')) {
			$sql_groupby = stristr ($sql, 'group by');
			$sql = substr ($sql, 0, strpos (strtolower ($sql), 'group by'));
			}
		
		# pokud je v sqlbase obsazen WHERE, musim to rozdelit
		if (function_exists("strripos")) {
			$poswhere = strripos ($sql, 'WHERE ');
			if ($poswhere>$lastfrom) {				
				$sql_where = substr ($sql,$poswhere+6);
				$sql = substr ($sql, 0, $poswhere);
				}
			}
		elseif (strpos (strtolower ($sql), 'where')) {
			$sql_where = substr (stristr ($sql, 'where'),6);
			$sql = substr ($sql, 0, strpos (strtolower ($sql), 'where'));
			}

		# WHERE - pridani podminky
		if (!is_array ($where) && isset ($where))
			$where = array ($where);
		if ($sql_where)
			$where[] = $sql_where;
		if ($where)
			$sql .= ' WHERE ' . implode (' AND ', $where);

		# pokud je v sqlbase GROUP BY, musis odriznutou cast pridat (zde)
		if ($sql_groupby)
			$sql .= ' ' .$sql_groupby;

		# ORDER BY - pridani trideni
		if (!$order) 
			$order = $this->order;
		foreach (explode (',', $order) as $partorder) {
			if (is_numeric ($partorder)) {
				if ($partorder < 0)
					$orders[] = (-1 * $partorder) . ' DESC';
				else
					$orders[] = $partorder;
				}
			}
		if (is_array ($orders))
			$sql .= ' ORDER BY ' . implode (', ', $orders);

		# LIMIT
		if ($limit != -1) {
			if (!is_numeric ($limit) || (int) $limit < 1)
				$limit = $this->limit;
			if ($limit != -1) {
				$sql .= ' LIMIT ';
				if (is_numeric ($limit_from) && (int) $limit_from > 1)
					$sql .= ($limit_from-1) * $limit . ', ';

				$sql .= $limit;
				}
			}
		
		$sql .= ';';

		# nechci pocet vsech radek - zdrzuje
		if ($nocalcrows)
			$sql = str_replace (' SQL_CALC_FOUND_ROWS', '', $sql);

		$this->cachesql = $sql;

		# SQL dotaz
		$this->DB->query ($sql, get_class ($this) . ' -> get');
		while ($radka = $this->DB->getRow ()) {
			$data[] = $radka;
			$this->cache[$radka['id']] = $radka;
			}
		
		# nactu si kolik by bylo celkove radek (pro lister)
		if (strpos ($this->sqlbase, 'SQL_CALC_FOUND_ROWS') && !$nocalcrows)
			$this->cachetotal = $this->DB->getRowsCount ();

		return ($data);
		}

	# ...................................................................
	function getCustom ($custom_sql = null, $where = null, $order = null, $limit = null, $limit_from = null) {
				
		$temp = $this->sqlbase;

		if ($custom_sql)
			$this->sqlbase = $custom_sql;

		$data = $this->get ($where, $order, $limit, $limit_from);

		$this->sqlbase = $temp;
		
		return ($data);
		}

	
	# ...................................................................
	# vrati definovane agregovane vysledky, tedy soucet, prumer, pocet atd vsech vysledku bez ohledu na limit
	function getGroupTotal ($where = null) {
		if (!$this->sqlgrouptotal)
			return (false);

		if (!is_array ($where) && isset ($where))
			$where = array ($where);

		$sql =  substr ($this->sqlbase, strpos (strtoupper ($this->sqlbase), ' FROM'));

		# pokud je v sqlbase obsazen GROUP BY, musim to rozdel (mozna i neco dalsiho)
		if (strpos (strtoupper ($sql), 'GROUP BY'))
			$sql = substr ($sql, 0, strpos (strtoupper ($sql), 'GROUP BY'));
		
		# pokud je v sqlbase obsazen WHERE, musim to rozdelit
		if (strpos (strtolower ($sql), 'where')) {
			$where[] = substr (stristr ($sql, 'where'),6);
			$sql = substr ($sql, 0, strpos (strtolower ($sql), 'where'));
			}

		# pokud je v sqlgrouptotal obsazen GROUP BY, musim to rozdelit
		if (strpos (strtoupper ($this->sqlgrouptotal), 'GROUP BY')) {
			$sql_groupby = ' ' . stristr ($this->sqlgrouptotal, 'GROUP BY');
			$sql_gt = substr ($this->sqlgrouptotal, 0, strpos (strtoupper ($this->sqlgrouptotal), 'GROUP BY'));
			}
		else {
			$sql_groupby = ' GROUP BY ' . $this->sqltable . '.id';
			$sql_gt = $this->sqlgrouptotal;
			}

		# WHERE - pridani podminky
		if (!is_array ($where) && isset ($where))
			$where = array ($where);
		if ($sql_where)
			$where[] = $sql_where;
		if ($where)
			$sql_where .= ' WHERE ' . implode (' AND ', $where) . ' ';

		# finalni dotaz
		$sql = $sql_gt . $sql . $sql_where . $sql_groupby; 


		# SQL dotaz
		$this->DB->query ($sql, get_class ($this) . ' -> getGroupTotal');
		return ($this->DB->getRow ());
		}
	
	# ...................................................................
	# stejne jako get (), ale vrati 1-n (parametr $count) nahodnych zaznamu
	function getRandom ($where = null, $order = null, $limit = null, $limit_from = null, $count = 1) {
		$data = $this->get ($where, $order, $limit, $limit_from);

		# chci cislo
		$count = 1 * (int) $count;
		# pokud mam dostatecny pocet zaznamu
		if ($this->cachetotal >= 1 && $this->cachetotal > $count) {
			# vyberu klice nahodnych polozek
			srand ();
			$keys = array_rand ($data, $count);

			if ($count > 1) {
				foreach ($keys as $value)
					$output[] = $data[$value];
				return ($output);
				}
			else
				return (array ($data[$keys]));
			}
		# zadny vysledek nebo chci stejny pocet jako mam = vyber vseho
		else
			return ($data);
		}

	# ...................................................................
	# vrati informace o aktualnim trideni
	function getExtra ($order = null) {		
		
		# budu pracovat jen s prvni hodnotou trideni z retezce "3, -5, 2, 11"
		if (strpos ($order, ',')) {
			$firstorder = substr ($order, 0, strpos ($order, ','));
			$restorder = substr ($order, strpos ($order, ',') );
			}
		else
			$firstorder = $order;
		if (!is_numeric ($firstorder))
			$firstorder = $this->order;
		
		# ORDER BY - pridani trideni
		if ($order < 0) {
			$output['order'] = -1*$firstorder;
			$output['order_type'] = 'down';
			$output['order_minus'] = 1;
			$output['order_other'] = $firstorder;
			$output['orderfull'] = (-1*$firstorder) . $restorder;
			$output['orderfull_other'] = $firstorder . $restorder;
			}
		else {
			$output['order'] = $firstorder;
			$output['order_type'] = 'up';
			$output['order_plus'] = 1;
			$output['order_other'] = -1*$firstorder;
			$output['orderfull'] = $firstorder . $restorder;
			$output['orderfull_other'] = (-1*$firstorder) . $restorder;
			}


		return ($output);
		}
	
	# ...................................................................
	function getTotal ($dataset = null, $values = null) {		
		if (!is_array ($values))
			return (false);
		if (!is_array ($dataset))
			return (false);
		
		foreach ($dataset as $row)
			foreach ($values as $key=>$funct) {
				if ($funct == 'count' && isset ($row[$key]))
					$output[$key]++;	
				if ($funct == 'sum' && isset ($row[$key]))
					$output[$key] += $row[$key];
				if ($funct == 'avg' && isset ($row[$key])) {
					$output[$key] += ((int) $row['id'] && $values['id']=='sum') ? $row['id'] * $row[$key] : $row[$key];
					$counter[$key]++;
					}
				}

		foreach ($values as $key=>$funct) {
			if ($funct=='avg' && $output[$key]) {
				$output[$key] = $output[$key] / (((int)$output['id'] && $values['id']=='sum') ? $output['id'] : $counter[$key]);
				}
			}

		return ($output);
		}
	
	# ...................................................................
	function getRowsCount ($result = null) {
		return ($this->cachetotal);
		}

	# ...................................................................
	function set ($set, $ids = null) {
		if (!is_array ($set))
			return (false);

		# priprava pro UDATE
		foreach ($set as $key=>$value)
			$sqltemp[] = $this->sqltable . '.' . $key . ' = ' . $value;
		
		# MULTI UPDATE
		if (is_array ($ids) && count ($ids)) {
			$sql = $this->sqlupdate . ' SET ' . implode (', ', $sqltemp) . ' WHERE ' . $this->sqltable . '.' . $this->idformat . ' IN ("' . implode ('", "', $ids) . '");';
			}
		# SINGLE UPDATE
		elseif (is_numeric ($ids)) {
			$sql = $this->sqlupdate . ' SET ' . implode (', ', $sqltemp) . ' WHERE ' . $this->sqltable . '.' . $this->idformat . ' = "' . $ids . '";';
			}
		# INSERT
		else {
			$sql = $this->sqlinsert . ' (' . implode (', ', array_keys ($set)) . ') VALUES (' . implode (', ', $set) . ');';
			$insert = true;
			}
		
		//$this->DB->query ('START TRANSACTION;');

		$go = $this->DB->getNumAffected ($this->DB->query ($sql));

		# kdyz byl insert, jeste nactu nove id
		if ($insert) 
			$ids = $this->DB->getId ();
		
		# musim volat sync()?
		if	($go)
			foreach (array_keys ($set) as $key=>$value)
				if (in_array ($key, $this->sync)) {
					if ($insert)
						$go = $this->syncInsert ($set, $ids);
					else
						$go = $this->syncUpdate ($set, $ids);
					break;
					}

		if ($go) {
			//$this->DB->query ('COMMIT;');
			
			# smazu si cache
			unset ($this->cache);
			unset ($this->cachetotal);
			
			return ($ids);
			}
		//else
			//$this->DB->query ('ROLLBACK;');
		
		return (false);
		}
		
	# ...................................................................
	function syncInsert ($set, $id) {
		return (true);
		}
	
	# ...................................................................
	function syncUpdate ($set, $oldata) {
		return (true);
		}
		
	# ...................................................................
	# $retunarray znaci ze vysledek bude pole [] = data, pri false se vrati jen 1. vysledek data
	function getId ($ids = null, $returnarray = true) {
		# prohledam cache, budu nacitat jen nove potrebne
		if (is_array ($ids))
			foreach ($ids as $key=>$id) {
				if ($this->cache[$id]) {
					$output[] = $this->cache[$id];
					unset ($ids[$key]);
					}
			}
		elseif ($ids) {
			if ($this->cache[$ids])
				$output[] = $this->cache[$ids];
			else
				$ids = array ($ids);
			}
		# nenasel jsem (vse), doctu potrebne
		if (count ($ids) && is_array ($ids)) {
			$data = $this->get ($this->sqltable . '.' . $this->idformat . ' IN("' . implode ('", "', $ids) . '")');
			$cache[$data[$this->idformat]] = $data;
			if (is_array ($data) && is_array ($output))
				$output = array_merge ($data, $output);
			elseif (is_array ($data))
				$output = $data;
			}

		# pripadne vratim i castecny vysledek
		if (is_array ($output)) {
			if ($returnarray)
				return ($output);
			else
				return ($output[0]);
			}

		return (false);
		}

	# ...................................................................
	function findId ($where, $returnonlyfirst = true) {
		if (!$where)
			return (null);
		$data = $this->get ($where);
		if ($data && $returnonlyfirst) {
			$data = reset ($data);
			return ($data['id']);
			}
		elseif (is_array ($data)) {
			foreach ($data as $key=>$value)
				$output[] = $value['id'];
			return ($output);
			}
		else
			return (null);
		}

	# ...................................................................
	function findIds ($where, $returnonlyfirst = true) {
		return ($this->findId ($where, false));	
		}
	
	# ...................................................................
	function createFulltextSubquery ($input = '', $columns = null, $separator_or = false) {
		# kdyz nemam vstup nebo hledane sloupce, konec
		if (!$input || (!$columns && !is_array ($this->fulltextcolumns)))
			return (null);

		# prevedu si seznam sloupcu na pole, pripadne nactu defaultni
		if ($columns && !is_array ($columns))
			$columns[] = $columns;	
		elseif (!is_array ($columns)) {
			$columns = $this->fulltextcolumns;
			}

		# vytvorim si subquery aplikovane na kazde hledane slovo
		foreach ($columns as $value) {		
			$sub[] = 'CAST(' . $value . ' AS CHAR)';
			}
		$word_query = 'CONCAT_WS(" ",' . implode (',', $sub) . ')';
		//$word_query = 'z.nazev';

		# vytvorim si pole hledanych slov
		$input = mb_strtolower (eregi_replace ('[^a-ž0-9 ]+', ' ', $input));
		$input = eregi_replace (' +', ' ', $input);
		$words = explode (' ', $input);

		# vytvoreni dotazu
		if (!is_array ($words)) 
			return (null);
		foreach ($words as $word)
			$query[] = $word_query . ' COLLATE utf8_general_ci LIKE "%' . $word . '%"';
		if ($separator_or)
			$output = '(' . implode (' OR ', $query) . ')';
		else
			$output = '(' . implode (' AND ', $query) . ')';
		
		return ($output);
		}

	}
?>