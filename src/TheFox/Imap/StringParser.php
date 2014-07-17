<?php

namespace TheFox\Imap;

class StringParser{
	
	const DEBUG = 1;
	
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
	}
	
	private function reset(){
		$this->argsId = -1;
		$this->args = array();
	}
	
	private function charNew($char = ''){
		if($this->argsMax === null || count($this->args) < $this->argsMax){
			if($this->argsId >= 0){
				if(static::DEBUG) fwrite(STDOUT, '    fix old /'.$this->args[$this->argsId].'/'."\n");
				if($this->args[$this->argsId] == '""'){
					$this->args[$this->argsId] = '';
				}
			}
			if(static::DEBUG) fwrite(STDOUT, '    new /'.$char.'/'."\n");
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
			if(static::DEBUG) fwrite(STDOUT, '    append /'.$char.'/'."\n");
			$this->args[$this->argsId] .= $char;
		}
	}
	
	public function parse(){
		$this->reset();
		
		$str = $this->str;
		$in = false;
		$prevChar = ' ';
		$endChar = '';
		
		if(static::DEBUG) fwrite(STDOUT, 'len: '.$this->len."\n");
		
		#for($pos = 0; $pos < $this->len; $pos++){ fwrite(STDOUT, sprintf('%2s', $pos).' '); } fwrite(STDOUT, "\n");
		
		#for($pos = 0; $pos < $this->len; $pos++){ $char = $str[$pos]; fwrite(STDOUT, sprintf('%2s', $char).' '); }; fwrite(STDOUT, "\n");
		
		#for($pos = 0; $pos < $this->len; $pos++){ fwrite(STDOUT, '/'.substr($str, 0, $pos).'/'."\n"); }; fwrite(STDOUT, "\n");
		
		for($pos = 0; $pos < $this->len; $pos++){
			$char = $str[$pos];
			$nextChar = ($pos < $this->len - 1) ? $str[$pos + 1] : '';
			
			if(static::DEBUG) fwrite(STDOUT, 'raw '.$pos.'/'.$this->len.'['.$this->argsId.']: /'.$char.'/'."\n");
			
			if($in){
				if(static::DEBUG) fwrite(STDOUT, '    in '."\n");
				if($char == $endChar){
					if(static::DEBUG) fwrite(STDOUT, '    is end char: '.(int)($this->argsMax === null).', '.(int)count($this->args).', '.(int)$this->argsMax.' '."\n");
					
					if($pos == $this->len - 1 || $this->argsMax === null || count($this->args) < $this->argsMax){
						$in = false;
						if(static::DEBUG) fwrite(STDOUT, '    close '."\n");
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
				if($this->argsMax === null || count($this->args) < $this->argsMax){
					if($char == '"'){
						if($nextChar == '"'){
							if(static::DEBUG) fwrite(STDOUT, '    new Aa (next /"/)'."\n");
							#$this->charNew('');
							$this->charNew('""');
							#$endChar = '"';
							#$in = true;
							$pos++;
						}
						else{
							if(static::DEBUG) fwrite(STDOUT, '    new Ab (next /'.$nextChar.'/)'."\n");
							$this->charNew();
							$endChar = '"';
							$in = true;
						}
						
					}
					elseif($char == ' '){
						if($nextChar == ' '){
							if(static::DEBUG) fwrite(STDOUT, '    new Ba (next / /)'."\n");
						}
						elseif($nextChar == '"'){
							if(static::DEBUG) fwrite(STDOUT, '    new Bb (next /"/)'."\n");
						}
						else{
							if(static::DEBUG) fwrite(STDOUT, '    new Bc (next /'.$nextChar.'/)'."\n");
							$this->charNew();
							$endChar = ' ';
							$in = true;
						}
					}
					else{
						if(static::DEBUG) fwrite(STDOUT, '    new C'."\n");
						$this->charNew($char);
						$endChar = ' ';
						$in = true;
					}
				}
				else{
					if(static::DEBUG) fwrite(STDOUT, '    limit'."\n");
					$this->charAppend($char);
				}
			}
			
			if(static::DEBUG) fwrite(STDOUT, '    text /'.$this->args[$this->argsId].'/'."\n");
			
			$prevChar = $char;
			
			#sleep(1);
		}
		
		#ve($this->args);
		#exit();
		
		return $this->args;
	}
	
}
