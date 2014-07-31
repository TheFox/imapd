<?php

namespace TheFox\Imap;

class Event{
	
	const TRIGGER_MAIL_ADD_PRE = 1000;
	const TRIGGER_MAIL_ADD_POST = 1010;
	
	private $trigger = null;
	private $object = null;
	private $function = null;
	private $returnValue = null;
	
	public function __construct($trigger = null, $object = null, $function = null){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		$this->trigger = $trigger;
		$this->object = $object;
		$this->function = $function;
	}
	
	public function getTrigger(){
		return $this->trigger;
	}
	
	public function getReturnValue(){
		return $this->returnValue;
	}
	
	public function execute(){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.''."\n");
		
		$object = $this->object;
		$function = $this->function;
		
		#ve($object);
		#ve($function);
		
		$args = array($this);
		
		if($object){
			$this->returnValue = call_user_func_array(array($object, $function), $args);
		}
		else{
			$this->returnValue = call_user_func_array($function, $args);
		}
		
		return $this->returnValue;
	}
	
}
