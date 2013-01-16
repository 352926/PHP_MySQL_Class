<?php
/**
 * User: Huangdd <352926@qq.com>
 * Date: 13-1-14
 * Time: 上午10:15
 */
class Model
{
    private $db;
    private $name;

    function __construct($name)
    {
        $this->name = $name;
        $this->db = DB::getInstance("127.0.0.1", 'root', 'hdd', 'test', 'hdd_');
    }

    public function get($title)
    {
        return $this->db->GetOne($this->name, $title, "id=16");
    }
}
