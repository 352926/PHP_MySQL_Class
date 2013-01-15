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
     * @param string $coding 数据库编码：GBK,UTF8,gb2312
     * @param bool $pconnect 永久链接/即时链接 默认即时链接
     * @param bool $tran 开启事务，需要MySQL支持InnoDB。
     */
    public function __construct($host, $user, $pwd, $database, $charset = "UTF8", $pconnect = false, $tran = false)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pwd = $pwd;
        $this->database = $database;
        $this->pconnect = $pconnect;
        $this->charset = $charset;
        $this->tran = $tran;
        $this->connect();
    }

    public function connect()
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

    public function Query($sql)
    {
        $rs = array();
        if ($this->logger) {
            $this->log($sql);
        }
        $result = $this->ExeSql($sql);
        if ($result) {
            while ($query = mysql_fetch_array($result)) {
                $rs[] = $query;
            }
            return $rs;
        } else {
            if (!$result) {
                if ($this->debug) {
                    $this->showErr("SQL错误:" . $this->sql);
                }
                return false;
            }
        }
    }

    public function ExeSql($sql)
    {
        if (!$sql) {
            if ($this->debug) {
                $this->showErr("没有SQL");
            }
            return false;
        }
        $this->sql = $sql;
        $result = mysql_query($this->sql, $this->conn);
        return $result;
    }

    /**
     * @param array $arr
     * $arr => format:
     * array('table'=>'table','fields'=>array(f1,f2...)|"`f1`,`f2`,...,'where'=>array(con1=>$v1,con2=>$v2,con3=>$v3)|" xx=oo AND oo=xx"))
     * NOTICE:默认使用AND链接WHERE条件。如有特殊情况，请勿使用数组方式传入WHERE条件。可直接传入sql如" xx=oo OR oo=xx"
     * @return bool|array
     */
    public function GetAll(Array $arr, $commit = true, $debug = false)
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
        $sql = sprintf($sql, $arr['fields'], $arr['table'], $arr['where']);
        if ($debug) {
            exit($sql);
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

    public function Delete($table, $where, $safe = true, $commit = true, $debug = false)
    {
        $sql = "DELETE FROM %s %s";
        if ($where) {
            $where = " WHERE " . $safe ? $where . " LIMIT 1" : $where;
        }
        $sql = sprintf($sql, $table, $where);
        if ($debug) {
            exit($sql);
        }
        if ($this->logger) {
            $this->log($sql);
        }
        return $this->Query($sql);
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
                    $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(",", $fields), implode(",", $content));
                    if ($debug) {
                        exit($sql);
                    }
                    return $this->Query($sql);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public function InsMulRow(Array $array, $commit = true, $debug = false)
    {
        if (
            empty($array) || !isset($array['table']) || !isset($array['fields']) || !is_array($array['fields'])
            || !isset($array['data']) || !is_array($array['data'] || !empty($array['data']))
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
        $sql = sprintf("INSERT INTO %s (`%s`) VALUES (%s)", $table, implode("`,`", $fields), implode("),("));
        if ($debug) {
            exit($sql);
        }
        $this->Query($sql);
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

    public function showErr($content)
    {
        exit("{$content}");
    }

    private function log()
    {
        //写入mysql.log
    }
}
