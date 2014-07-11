<?php

namespace TheFox\Imap;

class StringParser{
	
	private $str = '';
	private $len = 0;
	private $argsMax;
	private $argsId = -1;
	private $args = array();
	
	public function __construct($str, $argsMax = null){
		$this->str = $str;
		$this->str = trim($this->str);
		$this->len = strlen($this->str);
		$this->argsMax = $argsMax;
		
		#fwrite(STDOUT, 'parse /'.$this->str.'/ '.$this->len."\n");
		#fwrite(STDOUT, '      /');
		for($pos = 0; $pos < $this->len; $pos++){
			#fwrite(STDOUT, ''.($pos % 10).'');
		}
		#fwrite(STDOUT, '/'."\n");
	}
	
	private function reset(){
		$this->argsId = -1;
		$this->args = array();
	}
	
	private function charNew($char = ''){
		if($this->argsMax === null || count($this->args) < $this->argsMax){
			#fwrite(STDOUT, '    new /'.$char.'/'."\n");
			$this->argsId++;
			$this->args[$this->argsId] = $char;
		}
		else{
			$this->charAppend($char);
		}
	}
	
	private function charAppend($char){
		if($this->argsId == -1){
			$this->charNew($char);
		}
		else{
			#fwrite(STDOUT, '    append /'.$char.'/'."\n");
			$this->args[$this->argsId] .= $char;
		}
	}
	
	public function parse(){
		$this->reset();
		
		$str = $this->str;
		$in = false;
		$prevChar = ' ';
		$endChar = '';
		
		#fwrite(STDOUT, 'len: '.$this->len."\n");
		
		for($pos = 0; $pos < $this->len; $pos++){
			#fwrite(STDOUT, sprintf('%2s', $pos).' ');
		}
		#fwrite(STDOUT, "\n");
		
		for($pos = 0; $pos < $this->len; $pos++){
			$char = $str[$pos];
			#$char = 'x';
			#fwrite(STDOUT, sprintf('%2s', $char).' ');
		}
		#fwrite(STDOUT, "\n");
		
		for($pos = 0; $pos < $this->len; $pos++){
			#fwrite(STDOUT, '/'.substr($str, 0, $pos).'/'."\n");
		}
		#fwrite(STDOUT, "\n");
		
		for($pos = 0; $pos < $this->len; $pos++){
			$char = $str[$pos];
			$nextChar = ($pos < $this->len - 1) ? $str[$pos + 1] : '';
			
			#fwrite(STDOUT, 'raw '.$pos.'/'.$this->len.': /'.$char.'/  '.$this->argsId."\n");
			
			if($in){
				#fwrite(STDOUT, '    in '."\n");
				if($char == $endChar){
					#fwrite(STDOUT, '    is end char: '.(int)($this->argsMax === null).', '.(int)count($this->args).', '.(int)$this->argsMax.' '."\n");
					
					if($pos == $this->len - 1 || $this->argsMax === null || count($this->args) < $this->argsMax){
						$in = false;
						#fwrite(STDOUT, '    close '."\n");
					}
					else{
						$this->charAppend($char);
					}
				}
				else{
					$this->charAppend($char);
				}
			}
			else{
				if($char == '"'){
					#fwrite(STDOUT, '    new A '."\n");
					$this->charNew();
					$endChar = '"';
					$in = true;
				}
				elseif($char == ' '){
					if($nextChar == ' '){
						#fwrite(STDOUT, '    new Ba '."\n");
					}
					elseif($nextChar == '"'){
						#fwrite(STDOUT, '    new Bb '."\n");
					}
					else{
						#fwrite(STDOUT, '    new Bc '."\n");
						$this->charNew();
						$endChar = ' ';
						$in = true;
					}
				}
				else{
					#fwrite(STDOUT, '    new C '."\n");
					$this->charNew($char);
					$endChar = ' ';
					$in = true;
				}
			}
			
			#fwrite(STDOUT, '    text "'.$this->args[$this->argsId].'"'."\n");
			
			$prevChar = $char;
			
			#sleep(1);
		}
		
		#ve($this->args);
		#exit();
		
		return $this->args;
	}
	
}
