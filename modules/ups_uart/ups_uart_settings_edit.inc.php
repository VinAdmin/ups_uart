<?php
if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
}
$table_name='ups_uart_settings';

$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
if ($this->mode=='update') {
    $ok=1;
    // step: default
    if ($this->tab=='') {
        //updating '<%LANG_TITLE%>' (varchar, required)
        $rec['TITLE']=gr('title');
        if ($rec['TITLE']=='') {
            $out['ERR_TITLE']=1;
            $ok=0;
        }
        //updating 'port' (varchar)
         $rec['PORT']=gr('port');
        //updating 'speed' (varchar)
         $rec['SPEED']=gr('speed');
        //updating '<%LANG_UPDATED%>' (datetime)
         $rec['UPDATED'] = ups_uart::DateTime();
    }
    // step: data
    if ($this->tab=='data') {
    }
    //UPDATING RECORD
    if ($ok) {
        if (isset($rec['ID'])) {
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec=1;
            $rec['ID']=SQLInsert($table_name, $rec); // adding new record
        }
        $out['OK']=1;
    } else {
        $out['ERR']=1;
    }
}

// step: default
if ($this->tab=='') {
    if ($rec['UPDATED']!='') {
        $out['UPDATED_DATE']=$rec['UPDATED'];
    }
}

if ($this->tab=='data') {
    global $delete_id;
    if ($delete_id) {
        SQLExec("DELETE FROM ups_uart WHERE ID='".(int)$delete_id."'");
    }

    $properties=SQLSelect("SELECT * FROM ups_uart WHERE device_id='".$rec['ID']."' ORDER BY ID");
    $total=count($properties);
    for($i=0;$i<$total;$i++) {
        if ($this->mode=='update') {
            $properties[$i]['TITLE']=gr('title'.$properties[$i]['ID'],'trim');
            $properties[$i]['VALUE']=gr('value'.$properties[$i]['ID'],'trim');
            $properties[$i]['LINKED_OBJECT']=gr('linked_object'.$properties[$i]['ID'],'trim');
            $properties[$i]['LINKED_PROPERTY']=gr('linked_property'.$properties[$i]['ID'],'trim');
            $properties[$i]['LINKED_METHOD']=gr('linked_method'.$properties[$i]['ID'],'trim');
            SQLUpdate('ups_uart', $properties[$i]);
            if ($old_linked_object && $old_linked_object!=$properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property!=$properties[$i]['LINKED_PROPERTY']) {
                removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
            }
            if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
                 addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
            }
        }
    }
    $out['PROPERTIES']=$properties;   
}
if (is_array($rec)) {
    foreach($rec as $k=>$v) {
        if (!is_array($v)) {
            $rec[$k]=htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);
