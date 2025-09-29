<?php
/**
* UPS uart 
* @package project
* @author VinAdmin <ovvitalik@gmail.com>
* @copyright (c)
* @version 0.1 (wizard, 19:09:54 [Sep 22, 2025])
*/

class ups_uart extends module {
    public string $device = "/dev/ttyUSB0";
    const LIST = [
        'Вход','Выход','Напряжение батареи','Нагрузка','Частота','Батарея','Температура','Флаги'
    ];
    public int $lastMinute = -1;
    
    /**
    * ups_uart
    *
    * Module class constructor
    *
    * @access private
    */
    function __construct() {
        $this->name = "ups_uart";
        $this->title = "UPS uart";
        $this->version = "0.1";
        $this->module_category="<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }
    
    static public function DateTime() {
        return date('Y-m-d H:i:s');
    }
    
    /**
    * saveParams
    *
    * Saving module parameters
    *
    * @access public
    */
    function saveParams($data=1) {
        $p=array();
        if (IsSet($this->id)) {
            $p["id"]=$this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"]=$this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"]=$this->edit_mode;
        }
        if (IsSet($this->data_source)) {
            $p["data_source"]=$this->data_source;
        }
        if (IsSet($this->tab)) {
            $p["tab"]=$this->tab;
        }
        return parent::saveParams($p);
    }
    
    /**
    * getParams
    *
    * Getting module parameters from query string
    *
    * @access public
    */
    function getParams() {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id=$id;
        }
        if (isset($mode)) {
            $this->mode=$mode;
        }
        if (isset($view_mode)) {
            $this->view_mode=$view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode=$edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source=$data_source;
        }
        if (isset($tab)) {
            $this->tab=$tab;
        }
    }
    
    public function readPort() {
        $settings = SQLSelectOne("SELECT * FROM ups_uart_settings WHERE ID=1");
        
        // открываем порт для чтения/записи
        $fp = fopen($this->device, "r+");
        if (!$fp && $settings['ID']) {
            return false;
        }

        //обязательная настройка порта
        exec("stty -F {$settings['PORT']} {$settings['SPEED']} cs8 -cstopb -parenb raw -echo -icanon");
        // неблокирующий режим чтения
        stream_set_blocking($fp, false);
        
        return $fp;
    }
    
    function upsCmd($fp, $cmd){
        if(!$fp){
            return false;
        }
        
        // отправка
        $written = fwrite($fp, $cmd);
        fflush($fp); // сброс буфера, чтобы реально ушло

        if ($written === false) {
            echo "Ошибка записи в порт\n";
            return false;
        } else {
            echo "Отправлено $written байт\n";
        }

        // чтение строки
        $response = '';
        while (($c = fread($fp, 1)) !== false && $c !== "") {
            if ($c === "") break;
            if ($c === "\r") break;
            $response .= $c;
        }

        if ($response !== '') {
            $response = trim($response, "()"); // убираем скобки
            $parts = preg_split('/\s+/', $response);

            if(is_array($parts) && count($parts) > 6){
                unset($parts[3]);   //Удаление повторяющего значения, входного напряжения
                
                foreach ($parts as $k=>$v){
                    $title   = DBSafe(self::LIST[$k]); // защита от SQL-инъекций
                    $value   = DBSafe($v);
                    $updated = date('Y-m-d H:i:s');
                    
                    $sql = "INSERT INTO ups_uart (ID, TITLE, VALUE, device_id, UPDATED) 
                            VALUES (".(int)$k.", '$title', '$value', 1, '$updated')
                                ON DUPLICATE KEY UPDATE 
                                VALUE=VALUES(VALUE),
                                UPDATED=VALUES(UPDATED)";
                    SQLExec($sql);
                    
                    $rec = SQLSelectOne("SELECT * FROM ups_uart WHERE ID=".(int)$k." AND device_id=1");
                    if(!empty($rec['LINKED_OBJECT']) && !empty($rec['LINKED_PROPERTY'])){
                        sg($rec['LINKED_OBJECT'] . '.' . $rec['LINKED_PROPERTY'], $v);
                    }
                    
                    if(!empty($rec['LINKED_OBJECT']) && !empty($rec['LINKED_METHOD'])){
                        $currentMinute = (int)date('i');
                        
                        if($currentMinute !== $this->lastMinute){
                            sg($rec['LINKED_OBJECT'] . '.' . $rec['LINKED_METHOD']);
                        }
                    }
                }
            }
        } else {
            echo "Нет ответа\n";
        }
    }

    /**
    * Run
    *
    * Description
    *
    * @access public
    */
    function run() {
        global $session;
        $out=array();
        if ($this->action=='admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION']=$this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME']=$this->owner->name;
        }
        $out['VIEW_MODE']=$this->view_mode;
        $out['EDIT_MODE']=$this->edit_mode;
        $out['MODE']=$this->mode;
        $out['ACTION']=$this->action;
        $out['DATA_SOURCE']=$this->data_source;
        $out['TAB']=$this->tab;
        $this->data=$out;
        $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
        $this->result=$p->result;
    }
    
    /**
    * BackEnd
    *
    * Module backend
    *
    * @access public
    */
    function admin(&$out) {
        $this->getConfig();
        $out['API_URL']=$this->config['API_URL'];
        if (!$out['API_URL']) {
            $out['API_URL']='http://';
        }
        $out['API_KEY']=$this->config['API_KEY'];
        $out['API_USERNAME']=$this->config['API_USERNAME'];
        $out['API_PASSWORD']=$this->config['API_PASSWORD'];
        if ($this->view_mode=='update_settings') {
            global $api_url;
            $this->config['API_URL']=$api_url;
            global $api_key;
            $this->config['API_KEY']=$api_key;
            global $api_username;
            $this->config['API_USERNAME']=$api_username;
            global $api_password;
            $this->config['API_PASSWORD']=$api_password;
            $this->saveConfig();
            $this->redirect("?");
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE']=1;
        }
        if ($this->data_source=='ups_uart_settings' || $this->data_source=='') {
            if ($this->view_mode=='' || $this->view_mode=='search_ups_uart_settings') {
                $this->search_ups_uart_settings($out);
            }
            if ($this->view_mode=='edit_ups_uart_settings') {
               $this->edit_ups_uart_settings($out, $this->id);
            }
            if ($this->view_mode=='delete_ups_uart_settings') {
               $this->delete_ups_uart_settings($this->id);
               $this->redirect("?data_source=ups_uart_settings");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE']=1;
        }
        if ($this->data_source=='ups_uart') {
            if ($this->view_mode=='' || $this->view_mode=='search_ups_uart') {
                $this->search_ups_uart($out);
            }
            if ($this->view_mode=='edit_ups_uart') {
                $this->edit_ups_uart($out, $this->id);
            }
        }
    }

    /**
    * FrontEnd
    *
    * Module frontend
    *
    * @access public
    */
    function usual(&$out) {
        $this->admin($out);
    }
    
    /**
    * ups_uart_settings search
    *
    * @access public
    */
    function search_ups_uart_settings(&$out) {
        require(dirname(__FILE__).'/ups_uart_settings_search.inc.php');
    }
     
    /**
    * ups_uart_settings edit/add
    *
    * @access public
    */
     function edit_ups_uart_settings(&$out, $id) {
        require(dirname(__FILE__).'/ups_uart_settings_edit.inc.php');
     }
     
    /**
    * ups_uart_settings delete record
    *
    * @access public
    */
     function delete_ups_uart_settings($id) {
      $rec=SQLSelectOne("SELECT * FROM ups_uart_settings WHERE ID='$id'");
      // some action for related tables
      SQLExec("DELETE FROM ups_uart_settings WHERE ID='".$rec['ID']."'");
     }
     
    /**
    * ups_uart search
    *
    * @access public
    */
    function search_ups_uart(&$out) {
       require(dirname(__FILE__).'/ups_uart_search.inc.php');
    }
 
    /**
    * ups_uart edit/add
    *
    * @access public
    */
    function edit_ups_uart(&$out, $id) {
       require(dirname(__FILE__).'/ups_uart_edit.inc.php');
    }
 
    function propertySetHandle($object, $property, $value) {
        $this->getConfig();
        $table='ups_uart';
        $properties=SQLSelect("SELECT ID FROM $table WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
        $total=count($properties);
        if ($total) {
            for($i=0;$i<$total;$i++) {
                //to-do
            }
        }
    }
 
    function processSubscription($event, $details='') {
        $this->getConfig();
        if ($event=='SAY') {
            $level=$details['level'];
            $message=$details['message'];
            //...
        }
    }
 
    function processCycle($fp) {
        $this->getConfig();
        $this->upsCmd($fp, "Q1\r");
    }
 
    /**
    * Install
    *
    * Module installation routine
    *
    * @access private
    */
    function install($data='') {
        subscribeToEvent($this->name, 'SAY');
        parent::install();
    }
 
    /**
    * Uninstall
    *
    * Module uninstall routine
    *
    * @access public
    */
    function uninstall() {
        SQLExec('DROP TABLE IF EXISTS ups_uart_settings');
        SQLExec('DROP TABLE IF EXISTS ups_uart');
        parent::uninstall();
    }
 
    /**
    * dbInstall
    *
    * Database installation routine
    *
    * @access private
    */
    function dbInstall($data) {
        $create = <<<EOD
           ups_uart_settings: ID int(10) unsigned NOT NULL auto_increment
           ups_uart_settings: TITLE varchar(100) NOT NULL DEFAULT ''
           ups_uart_settings: PORT varchar(255) NOT NULL DEFAULT ''
           ups_uart_settings: SPEED varchar(255) NOT NULL DEFAULT ''
           ups_uart_settings: UPDATED datetime
           ups_uart: ID int(10) unsigned NOT NULL auto_increment
           ups_uart: TITLE varchar(100) NOT NULL DEFAULT ''
           ups_uart: VALUE varchar(255) NOT NULL DEFAULT ''
           ups_uart: device_id int(10) NOT NULL DEFAULT '0'
           ups_uart: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
           ups_uart: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
           ups_uart: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
           ups_uart: UPDATED datetime
        EOD;
        parent::dbInstall($create);
        
        $rec = SQLSelectOne("SELECT ID FROM ups_uart_settings WHERE ID=1");
        if (!$rec['ID']) {
            $rec = [
                'TITLE' => 'UPS',
                'PORT' => $this->device,
                'SPEED' => 2400,
                'UPDATED' => ups_uart::DateTime()
            ];
            SQLInsert('ups_uart_settings', $rec);
        }
        
        // создаём класс, если его нет
        $class = SQLSelectOne("SELECT ID FROM classes WHERE TITLE='SDevices'");
        if (!$class['ID']) {
            $class = [
                'TITLE' => 'SDevices',
                'DESCRIPTION' => 'General Devices Class',
            ];
            $class['ID'] = SQLInsert('classes', $class);
        }
        
        $obj = SQLSelectOne("SELECT ID FROM objects WHERE TITLE='UpsUart'");
        if (!$obj['ID']) {
            $obj = [
                'TITLE' => 'UpsUart',
                'CLASS_ID' => $class['ID'],
                'DESCRIPTION' => 'Хранит данные полученные с UPS',
            ];
            $obj['ID'] = SQLInsert('objects', $obj);
        }
        
        $prop = SQLSelectOne("SELECT * FROM properties WHERE TITLE='inputVoltage' AND CLASS_ID=".$class['ID']);
        if (!$prop['ID']) {
            $prop = [];
            $prop['TITLE'] = 'inputVoltage';
            $prop['CLASS_ID'] = $class['ID'];
            $prop['KEEP_HISTORY'] = 0;
            $prop['DATA_TYPE'] = 0; // логическое
            $prop['DESCRIPTION'] = 'Bходное напряжение';
            $prop['ID'] = SQLInsert('properties', $prop);
            $prop['ID'] = SQLInsert('properties', $prop);
            $prop = '';
        }
        
        $prop = SQLSelectOne("SELECT * FROM properties WHERE TITLE='outputVoltage' AND CLASS_ID=".$class['ID']);
        if (!$prop['ID']) {
            $prop = [];
            $prop['TITLE'] = 'outputVoltage';
            $prop['CLASS_ID'] = $class['ID'];
            $prop['KEEP_HISTORY'] = 0;
            $prop['DATA_TYPE'] = 0; // логическое
            $prop['DESCRIPTION'] = 'Bходное напряжение';
            $prop['ID'] = SQLInsert('properties', $prop);
            $prop = '';
        }
        
        $prop = SQLSelectOne("SELECT * FROM properties WHERE TITLE='frequency' AND CLASS_ID=".$class['ID']);
        if (!$prop['ID']) {
            $prop = [];
            $prop['TITLE'] = 'frequency';
            $prop['CLASS_ID'] = $class['ID'];
            $prop['KEEP_HISTORY'] = 0;
            $prop['DATA_TYPE'] = 0; // логическое
            $prop['DESCRIPTION'] = 'Частота в сети';
            $prop['ID'] = SQLInsert('properties', $prop);
            $prop = '';
        }
        
        $prop = SQLSelectOne("SELECT * FROM properties WHERE TITLE='load' AND CLASS_ID=".$class['ID']);
        if (!$prop['ID']) {
            $prop = [];
            $prop['TITLE'] = 'load';
            $prop['CLASS_ID'] = $class['ID'];
            $prop['KEEP_HISTORY'] = 0;
            $prop['DATA_TYPE'] = 0; // логическое
            $prop['DESCRIPTION'] = 'Нагрузка';
            $prop['ID'] = SQLInsert('properties', $prop);
            $prop = '';
        }
        
        SQLExec("ALTER TABLE ups_uart MODIFY ID INT NOT NULL;");
    }
}
