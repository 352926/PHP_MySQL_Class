<?php
/**
 * User: Huangdd <352926@qq.com>
 * Date: 13-1-13
 * Time: 下午1:16
 */
class DB
{
    private $host;
    private $user;
    private $pwd;
    private $database;
    private $pre;
    private $pconnect;
    private $conn;
    private $charset;
    private $debug = true;
    private $sql = '';
    private $logger = true;
    private $logpath = '';
    private $tran = false;
    private $begin = false;

    /**
     * @param $host 数据库主机
     * @param $user 数据库连接用户名
     * @param $pwd 数据库连接密码
     * @param $database 数据库名
     * @param $pre 数据库前缀
     * @param string $coding 数据库编码：GBK,UTF8,gb2312
     * @param bool $pconnect 永久链接/即时链接 默认即时链接
     * @param bool $tran 开启事务，需要MySQL支持InnoDB。
     */
    function __construct($host, $user, $pwd, $database, $pre, $charset = "UTF8", $pconnect = false, $tran = false)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pwd = $pwd;
        $this->database = $database;
        $this->pre = $pre;
        $this->pconnect = $pconnect;
        $this->charset = $charset;
        $this->tran = $tran;
        $this->connect();
    }

    public static function getInstance($host, $user, $pwd, $database, $pre, $charset = "UTF8", $pconnect = false)
    {
        $class = __CLASS__;
        $db = new $class($host, $user, $pwd, $database, $pre, $charset, $pconnect);
        return $db;
    }

    private function connect()
    {
        $connect = $this->pconnect ? "mysql_pconnect" : "mysql_connect";
        $this->conn = $connect($this->host, $this->user, $this->pwd);

        if (!mysql_select_db($this->database, $this->conn)) {
            if ($this->debug) {
                $this->showErr("数据库不可用：" . $this->database);
            }
            if ($this->logger) {
                $this->log("xxx", $this->logpath);
            }
        }
        mysql_query("SET NAMES $this->charset");
        if ($this->tran) {
            $this->Begin();
        }
    }

    function __destruct()
    {
        mysql_close() or die('MySQL ERROR: ' . mysql_error());
    }

    /**
     * @param $sql
     * @return array|bool
     * @NOTICE 使用Query()方法时，会自动将数据用mysql_fetch_array转换成数组返回。
     * 如果要得到resource类型，请使用ExeSQL()方法
     */
    public function Query($sql)
    {
        $rs = array();
        if ($this->logger) {
            $this->log($sql);
        }
        $result = $this->ExeSql($sql);
        if (is_resource($result)) {
            while ($query = mysql_fetch_array($result)) {
                $rs[] = $query;
            }
            return $rs;
        } elseif ($result) {
            return $result;
        } else {
            if (!$result) {
                if ($this->debug) {
                    $this->showErr("SQL错误:" . $this->sql);
                }
                return false;
            }
        }
    }

    /**
     * @param $sql
     * @return bool|resource
     * 执行sql，返回结果不做任何处理
     */
    public function ExeSql($sql)
    {
        if ($sql) {
            $this->sql = $sql;
            $result = mysql_query($this->sql, $this->conn);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * @param array $arr
     * $arr => format:
     * array('table'=>'table','fields'=>array(f1,f2...)|"`f1`,`f2`,...,'where'=>array(con1=>$v1,con2=>$v2,con3=>$v3)|" xx=oo AND oo=xx"))
     * NOTICE:默认使用AND链接WHERE条件。如有特殊情况，请勿使用数组方式传入WHERE条件。可直接传入sql如" xx=oo OR oo=xx"
     * @return bool|array
     */
    public function GetAll(Array $arr, $debug = false)
    {
        if (empty($arr) || !isset($arr['table']) || empty($arr['table'])) {
            return false;
        }
        if (!isset($arr['fields']) && empty($arr['fields'])) {
            $arr['fields'] = "*";
        } elseif (is_array($arr['fields'])) {
            $arr['fields'] = "`" . implode("`,`", $arr['fields']) . "`";
        }
        if (!isset($arr['where']) && empty($arr['where'])) {
            $arr['where'] = "";
        } elseif (is_array($arr['where'])) {
            $where_arr = array();
            if (is_array($arr['where'])) {
                foreach ($arr['where'] as $k => $v) {
                    $where_arr[] = "`{$k}` = '{$v}'";
                }
            }
            if (!empty($where_arr)) {
                $arr['where'] = " WHERE " . implode(" AND ", $where_arr);
            } else {
                $arr['where'] = "";
            }
        } else {
            $arr['where'] = "WHERE " . $arr['where'];
        }

        $sql = "SELECT %s FROM `%s` %s";
        $sql = sprintf($sql, $arr['fields'], $this->pre . $arr['table'], $arr['where']);
        if ($debug) {
            return $sql;
        }
        if ($this->logger) {
            $this->log($sql);
        }
        $result = $this->Query($sql);
        if ($result) {
            return $result;
        } else {
            if ($this->debug) {
                $this->showErr("数据库操作错误:<br>" . $sql);
            }
        }
    }

    /**
     * @param $table
     * @param $field 只能传递要返回内容的那一个字段
     * @param string $where|array $where
     * @param bool $commit
     * @param bool $debug
     * @return bool|string
     */
    public function GetOne($table, $field, $where = '', $debug = false)
    {
        if (!empty($table) && !empty($field)) {
            if (stristr($field, ',')) {
                return false;
            }
            $condr = array();
            if (is_array($where) && !empty($where)) {
                foreach ($where as $k => $v) {
                    $condr[] = "`{$k}`='{$v}'";
                }
            } elseif (!empty($where)) {
                $condr[] = $where;
            }
            $sql = sprintf("SELECT `%s` FROM `%s` %s LIMIT 1", $field, $this->pre . $table, empty($condr) ? "" : " WHERE " . implode(" AND ", $condr));
            if ($debug) {
                return $sql;
            }
            $data = $this->ExeSql($sql);
            if ($data) {
                $rs = mysql_fetch_assoc($data);
                return isset($rs[$field]) ? $rs[$field] : false;
            } elseif ($this->debug) {
                $this->showErr("SQL错误：<br>" . $sql);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $table
     * @param string $fields|array $fields 传递自己构造的字段sql或者数组
     * @param string $where|array $where 传递自己构造的条件sql或者数组
     * @param bool $commit
     * @param bool $debug
     * @return array|bool|string
     */
    public function GetRow($table, $fields, $where = '', $debug = false)
    {
        if (!empty($table) && !empty($fields)) {
            $condr = array();
            if (is_array($fields)) {
                $fields = "`" . implode("`,`", $fields) . "`";
            }
            if (is_array($where) && !empty($where)) {
                foreach ($where as $k => $v) {
                    $condr[] = "`{$k}`='{$v}'";
                }
            } elseif (!empty($where)) {
                $condr[] = $where;
            }
            $sql = sprintf("SELECT %s FROM `%s` %s LIMIT 1", $fields, $this->pre . $table, empty($condr) ? "" : " WHERE " . implode(" AND ", $condr));
            if ($debug) {
                return $sql;
            }
            $data = $this->ExeSql($sql);
            if ($data) {
                $rs = mysql_fetch_assoc($data);
                return $rs;
            } elseif ($this->debug) {
                $this->showErr("SQL错误：<br>" . $sql);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param array $array array('table'=>array('f1'=>'value1','f2'=>'value2'))
     * @param string $where
     * @param bool $commit
     * @param bool $debug
     * @return bool|resource|string
     */
    public function UpdRow(Array $array, $where = '', $commit = true, $debug = false)
    {
        if (!empty($array)) {
            $table = key($array);
            $set = array();
            if (is_array($array[$table]) && !empty($array[$table])) {
                foreach ($array[$table] as $k => $v) {
                    $set[] = "`{$k}`='{$v}'";
                }
                $sql = sprintf("UPDATE `%s` SET %s %s", $this->pre . $table, implode(" , ", $set), empty($where) ? "" : " WHERE " . $where);
                if ($debug) {
                    return $sql;
                }
                $rs = $this->ExeSql($sql);
                if ($rs && $this->tran && $commit) {
                    $this->Commit();
                }
                return $rs;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $table
     * @param $where
     * @param bool $safe 开启后只删除一条，自动加入limit 1限制
     * @param bool $commit
     * @param bool $debug
     * @return bool|resource|string
     */
    public function Delete($table, $where, $safe = true, $commit = true, $debug = false)
    {
        $sql = "DELETE FROM `%s` %s";
        if ($where) {
            $where = " WHERE " . $where;
        }
        $sql = sprintf($sql . ($safe ? $where . " LIMIT 1" : $where), $this->pre . $table, $where);
        if ($debug) {
            return $sql;
        }
        $rs = $this->ExeSql($sql);

        if ($this->logger) {
            $this->log($sql);
        }
        if ($rs) {
            if ($this->tran && $commit) {
                $this->Commit();
            }
            return $rs;
        } elseif ($this->debug) {
            $this->showErr("SQL错误：<br>" . $sql);
        } else {
            return false;
        }
    }

    /**
     * @param $table
     */
    public function TableExists($table)
    {
        if (!empty($table)) {
            $sql = sprintf("SHOW TABLES LIKE '%s'", $table);
            $rs = mysql_fetch_assoc($this->ExeSql($sql));
            return $rs ? true : false;
        } else {
            return false;
        }
    }

    /**
     * @param array $data
     * $data = array("table"=>array("oo"=>"xx","xx"=>"oo"));
     * @param bool $commit
     * @param bool $debug
     */
    public function InsRow(Array $data, $commit = true, $debug = false)
    {
        if (!empty($data) && key($data)) {
            $content = array();
            $table = key($data);
            if (is_array($data[$table]) && !empty($data[$table])) {
                $fields = array();
                foreach ($data[$table] as $k => $v) {
                    $content[] = "'{$v}'";
                    $fields[] = "`{$k}`";
                }
                if (!empty($fields) && !empty($content)) {
                    $sql = sprintf("INSERT INTO `%s` (%s) VALUES (%s)", $this->pre . $table, implode(",", $fields), implode(",", $content));
                    if ($debug) {
                        return $sql;
                    }
                    $rs = $this->ExeSql($sql);
                    if ($rs && $this->tran && $commit) {
                        $this->Commit();
                    }

                    if ($rs) {
                        if ($this->tran && $commit) {
                            $this->Commit();
                        }
                        return mysql_insert_id($this->conn);
                    } elseif ($this->debug) {
                        $this->showErr("SQL错误：<br>" . $sql);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    /**
     * @param array $array
     * array('table'=>'table1','fields'=>array(f1,f2,f3,...,fn),'data'=>array(array(v1,v2,...,vn),array(v1,v2,...,vn)))
     * @param bool $commit
     * @param bool $debug
     * @return bool|resource|string
     */
    public function InsMulRow(Array $array, $commit = true, $debug = false)
    {
        if (
            empty($array) || !isset($array['table']) || !isset($array['fields']) || !is_array($array['fields'])
            || !isset($array['data']) || !is_array($array['data']) || empty($array['data'])
        ) {
            return false;
        }
        $table = $array['table'];
        $fields = $array['fields'];
        $data = $array['data'];
        $item = array();
        foreach ($data as $val) {
            if (!empty($val)) {
                $item[] = "'" . implode("','", $val) . "'";
            }
        }
        $sql = sprintf("INSERT INTO `%s` (`%s`) VALUES (%s)", $this->pre . $table, implode("`,`", $fields), implode("),(", $item));
        if ($debug) {
            return $sql;
        }
        $rs = $this->ExeSql($sql);

        if ($rs) {
            if ($this->tran && $commit) {
                $this->Commit();
            }
            return mysql_insert_id($this->conn);
        } elseif ($this->debug) {
            $this->showErr("SQL错误：<br>" . $sql);
        } else {
            return false;
        }
    }

    /**
     * @param $table
     * @param string $type full|fields
     * @return array|bool
     */
    public function ShowColumn($table, $type = "fields")
    {
        if ($table && $type) {
            if ($this->TableExists($table)) {
                $data = $this->Query("SHOW FULL COLUMNS FROM `{$table}`");
                if ($data) {
                    if ($type == 'full') {
                        return $data;
                    } elseif ($type == 'fields') {
                        $result = array();
                        foreach ($data as $v) {
                            $result[] = $v['Field'];
                        }
                        return $result;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function Begin()
    {
        if ($this->tran) {
            if (!$this->begin) {
                return $this->ExeSql("COMMIT");
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    public function Commit()
    {
        if ($this->tran) {
            if (!$this->begin) {
                $this->Begin();
            }
            return $this->ExeSql("COMMIT");
        } else {
            return false;
        }
    }

    public function Back()
    {
        if ($this->tran) {
            return $this->ExeSql("ROLLBACK");
        } else {
            return false;
        }
    }

    private function showErr($content)
    {
        exit("{$content}");
    }

    private function log()
    {
        //写入mysql.log
    }
}
