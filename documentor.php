<?php
//%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// This script will take a class object and create documentation for it. It is an improvement
// over just using DocBlocks and PHP Documentor in the following ways:
// Because this code uses PHP Reflection classes to take a live look at the existing object, 
// the description is always correct and does not rely on the developer keeping the 
// DocBlock up-to-date. This script will add to the documentation by reading the DocBlock, 
// if available, and will add descriptive information if appropriate. Additionally, I have 
// added one more feature; By creating a new Doc tag (@document) I provide the ability to have 
// the document display the definition of arrays that are defined within a method. 
// This is useful for utility methods like <TABLE> creators, where there are a lot of parameters 
// that can be set to redefine default values. With this script you can see what parameters are 
// available and their default values.  
// 
// Author: Marc Zev
// Version 0.9
// History: The whDocumentor object was extracted from the Work Horse Framework, written by 
//          Marc Zev, and modified to run stand-alone. Because of the extraction, the CSS in 
//          this version is not completely consistent or pretty. When run from within the 
//          framework the output is cleaners. There are plans to clean-up display in future 
//          versions. 


// -----------
// If using a class in a separate file... replace the following line with  the appropriate include statement.
//include_once '/home/misc_domains/vsmedia.com/webdev/mz/lib/php/classes/class_whDocumentor.php';


// The following will display the documentation for the [included] whDocumentor object.
$cls = new whDocumentor();
$documentor = new whDocumentor($cls);
$documentor->document(null, false);
//%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * whDocumentor
 *
 * This class is the ancestor from which all other classes beckon forth. It is named for the oldest hominin ancestor.
 * line 2
 *
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
class whDocumentor 
{

// properties
   private $data = array();
   private $class = false; 
   private $thisClass = false; 
   private $thisCode = false; 
   private $thisCodeMethods = false;
   private $thisClassMethods = false;
   private $thisClassMethodParameters = false;
   private $thisClassMethodModifiers = false;
   private $thisClassMethodModifiers2 = false;
   private $thisClassMethodDocBlock = false;
   private $thisClassInterfaces = false;
   private $thisClassParent = false;
   private $thisClassNamespaceName = false;
   
// 
// ... List of expected tags in Doc Block. If the value is FALSE then the tag is ignored. If not 
//     FALSE then the value is the label used in output.
//
   private $docBlockTags = array ('abstract'=>false, 
   									'access'=>false, 
									'author'=>'Author: ',
									'category'=>false, 
									'copyright'=>false,
									'deprecated'=>false, 
									'example'=>false,
									'final'=>false, 
									'filesource'=>false,
									'global'=>'Global: ',
									'ignore'=>false, 
									'internal'=>false, 
									'license'=>false, 
									'link'=>false,
									'method'=>false,
									'name'=>false,
									'package'=>false, 
									'param'=>false, 
									'property'=>false,
									'return'=>false,
									'see'=>false, 
									'since'=>false, 
									'static'=>false, 
									'staticvar'=>false, 
									'subpackage'=>false,
									'todo'=>false, 
									'tutorial'=>false,
									'uses'=>false,
									'var'=>false, 
									'version'=>'Version: ', );
									
// 
// ... List of expected inline tags. If the value is FALSE then the tag is ignored. If not 
//     FALSE then the value is the label used in output.
// ... NOT IMPLEMENTED!
//
   private $inlineTags = array ('example'=>false, 
   								'id'=>false, 'internal'=>false, 'inheritdoc'=>false,
								'link'=>false,
								'source'=>false,
								'toc'=>false, 'tutorial'=>false, );

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * __construct
 *
 * Constructor method
 *
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
public function __construct($class=false)
{
	if (!$class) $class = $this;
	
	$this->loadClass($class);
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * cleanWhiteSpace
 *
 * @author Marc Zev
 */ 
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
static public function cleanWhiteSpace($string)
{
	return $ret = trim( self::remove_doublewhitespace( self::remove_whitespace_feed($string) ) );
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * disectDocBlock
 * 
 * Decompose a doc block into its parts and tags. This is used to figure out what to display.
 * 
 * @document return The indices of the array that gets returned
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
private function disectDocBlock($docBlock)
{
	$return = array('title'=>false, 'description'=>false, 'tags'=>array(),);

	if (!is_array($docBlock)) $docBlock = explode(chr(10), $docBlock);
	
	$title = $description = false;
	$more = $tags = $outline = array();
	$nullFound = true;
	$oIndex = 0;
	
	foreach($docBlock as $idx=>&$line)
	{
		$line = self::cleanWhiteSpace($line);
		if (substr($line, 0, 3) == '/**')
		{
			unset($docBlock[$idx]);
			continue;
		}
		else if (substr($line, 0, 2) == '*/')
		{
			unset($docBlock[$idx]);
			continue;
		}
		else if ($line == '*') $line = null;
		else if (substr($line, 0, 2) == '* ') $line = substr($line, 2);
		
		
		if (substr($line,0,1) == '@')
		{
			$tags[] = $line;
			continue;
		}
		else if (is_null($line)) $nullFound = true;
		else
		{
			if ($nullFound) 
			{
				++$oIndex;
				$outline[$oIndex] = $line; 
			}
			else 
			{
				$outline[$oIndex] .= ' ' . $line; 
			}
			
			$nullFound = false;
		}

		if (!is_null($line))
		{
			if (substr($line,0,1) != '@')
			{
				if (!$title) $title = $line;
				else if (!$description) $description = $line;
				else $more[] = $line; 
			}
			else $tags[] = $line;
		}
	}
	
	$title = isset($outline[1]) ? $outline[1] : null;
	
	$ret = $param = array();
	foreach($tags as $tag)
	{
		if(stripos($tag, '@return') === 0) 
		{
			$tg = explode(' ', $tag, 3);
			$ret['type'] = isset($tg[1]) ? $tg[1] : null;
			$ret['notes'] = isset($tg[2]) ? $tg[2] : null;
		} 

		if(stripos($tag, '@param') === 0) 
		{

			$tag = self::cleanWhiteSpace($tag);
			$tg = explode(' ', $tag, 4);

			$par['type'] = isset($tg[1]) ? $tg[1] : null;
			$par['variable'] = str_replace('$', null, (isset($tg[2]) ? $tg[2] : null));
			$par['notes'] = isset($tg[3]) ? $tg[3] : null;
			$param[$title][$par['variable']] = $par;
		} 
	}
	
	
	$return = array('title'=>isset($outline[1]) ? $outline[1] : null,
				'description'=>isset($outline[2]) ? $outline[2] : null,
				'tags'=>$tags, 'return'=>$ret, 'parameters'=>$param);
				
	return $return;
}
	

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * document
 * 
 * Used to dynamicaly create documention for any class extended from Toumai. Calling this method produces the report
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
public function document($method='*', $printDocBlock=false)
{
	$thisClass = $this->loadClass();

	$docClass = $thisClass->getDocComment();
	$disectedClassBlock = $this->disectDocBlock($docClass);
	
	$rMethod = false;

//----------------------------------------------------------------------------------------------------------
// ... Display Class-level information
//		
	echo '<div class="class-display" style="background-color: #ffffff;">' . "\n";
	
	echo "<h1 style='text-align:left;'>Class: $thisClass->name</h1>" . "\n";
	if (!empty($disectedClassBlock['description'])) echo "<h2  style='color: black; margin: 0 20px; text-align:left;'>" . $disectedClassBlock['description'] . "</h2>" . "\n";


    if (is_object($this->thisClassParent))  echo '<h3 style="margin-left: 3em;">Parent: ' . $this->thisClassParent->getName() .'</h3>';
    if (!empty($this->thisClassNamespaceName)) echo 'h3 style="margin-left: 3em;">Namespace: ' . $this->thisClassNamespaceName .'</h3>';

//----------------------------------------------------------------------------------------------------
// ... Display Class Interfaces
//		

	if (count($this->thisClassInterfaces) > 0) 
	{
		echo '<h2>Interfaces</h2><blockquote>' . "\n";
		foreach ($thisClassInterfaces as $idx=>$interface)
		{
			echo $interface->getName() . '<br/>';
		}
		echo '</blockquote>' . "\n";
	}

	echo "<h3 style='text-align:left; margin: 10px 30px; '>Source: " . $thisClass->getFileName() . "</h3><br/>" . "\n";
	if ($printDocBlock) echo "<div class='shadow' style='padding: 10px;'><pre>" . $docClass . "</pre></div>" . "\n";
	
	$dbText = null;
	
	foreach ($this->docBlockTags as $tag=>$label)
	{
		if (!$label) continue;
		$found = false;
		foreach ($disectedClassBlock['tags'] as $tagLine)
		{
			if (substr($tagLine, 0, strlen('@'.$tag)) == '@'.$tag)
			{ 
				$found = true;
				if ($found) $tag = trim(substr($tagLine, strlen('@'.$tag)));
				break;
			}
		}
		if ($found) $dbText .= "{$label} {$tag}<br/>" . "\n";
	}
	
	if (!empty($dbText)) 
	{
		echo "<div class='shadow' style='padding: 10px;; background-color:#ffe;'>";
//		echo '<h4>Doc-Block</h4><blockquote>';
		echo $dbText;
		echo '</blockquote></div>' . "\n";
	}
	
	if (empty($method) || $method == '*')
	{
		$thisClassMethods = $this->thisClassMethods;
	}
	else
	{
		if ( ($thisClassMethod = $this->getThisClassMethod($method)) )
		{
			$thisClassMethods = array($thisClassMethod);
		}
		else 
		{
			$thisClassMethods = array();
			echo "<h1>`{$method}` is not a valid method for this class.</h1>";
		}
	}


	echo '</div>' . "\n";

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ... Display Method-level information
//		

	if (count($thisClassMethods) > 0) 
	{
		echo '<div class="methods-display" style="margin: 10px 40px; padding: 10px; border: 1px solid #ddd; background-color: #fffffc;">' . "\n";
		$bgc = array('#FAEFE1', '#FAE1F8');
		$ibgc = 1;

		echo '<h3>Quick Links</h3>';
		foreach($thisClassMethods as $methodName=>$m)
		{
			echo '&nbsp;Method: <a href="#_' . $methodName . '">' . $methodName . '</a><br/>';
		}
		
		foreach($thisClassMethods as $methodName=>$m)
		{
			if (empty($m)) continue;
			
			$ibgc = 1 - $ibgc;
			$docMethod = $this->thisClassMethodDocBlock[$methodName];  // Method's Doc Block
			$disectedMethodBlock = $this->disectDocBlock($docMethod);

	
			echo '<div class="method-display" style="margin: 5px 5px; padding: 5px; border: 1px solid #aaf; background-color: '.$bgc[$ibgc].';">' . "\n";
			if ($printDocBlock) echo "<div class='shadow' style='padding: 10px;'><pre>" . $docMethod . "</pre></div>" . "\n";
			echo '<a name="_' . $methodName . '">';
			
			$flgMod = true;
			if (isset($this->thisClassMethodModifiers[$methodName]))
			{
				if (count($this->thisClassMethodModifiers[$methodName]) > 0 ) $flgMod =  ' &lt;' . implode(', ', $this->thisClassMethodModifiers[$methodName]) . '&gt;';
				else $flgMod = null;
			}
			
			if ($m->isConstructor()) $flgMod .= ' &lt;Constructor&gt; ';
			if ($m->isDestructor()) $flgMod .= ' &lt;Destructor&gt; ';
			
			echo '<h2 style="text-align: left; color:dodgerblue;"><span style="color: black;">Method:</span> ' . $methodName . $flgMod . '</h2>' . "\n";
			echo '</a>';
			
			if (!empty($disectedMethodBlock['description'])) echo "<h3  style='color: black; margin: 0 20px;'>" . $disectedMethodBlock['description'] . "</h3>" . "\n";

//
// ... Print relevant doc block data for method
//	
			$dbText = null;
			foreach ($this->docBlockTags as $tag=>$label)
			{
				if (!$label) continue;
				$found = false;
				foreach ($disectedMethodBlock['tags'] as $tagLine)
				{
					list($tg, $ln) = explode(' ', $tagLine, 2);
					if (trim($tg) == '@'.$tag)
					{ 
						$found = true;
						if ($found) $tag = trim($ln);
						break;
					}
				}
				if ($found) $dbText .= "{$label} {$tag}<br/>" . "\n";
			}
			 
			if (!empty($dbText)) 
			{ 
				echo "<div class='shadow' style='padding: 10px; margin-top: 10px; background-color:#eee;'>";
				echo $dbText;
				echo "</blockquote></div>" . "\n";
			}
	
//
// ... Print information for each parameter
//	
			$methodParams = $this->thisClassMethodParameters[$methodName]; // Method's Parameters

			if (count($methodParams) > 0)
			{
				$c = $ll = array();
				$ic = 0;
				$cc = null;
				$first = true;
								
				foreach ($methodParams as $p)
				{
					if (!is_object($p)) continue;
					
					$l = $c = array();
					
					if ($p->isArray())  $ay = ' array ';
					else  $ay = null;
					
					$cType = null;
					if (method_exists($p, 'getText')  && !is_null($p->getType())) 
					{
						$l[] = '<span style="">' . $p->getType() . $ay . '</span>';
						$cType = '<span style="color:#1c6;">' . $p->getType() . ' ' . $ay . '</span>';
					}
					else
					{
						if (isset($disectedMethodBlock['parameters'][$methodName][$p->name]['type'])) $typ = $disectedMethodBlock['parameters'][$methodName][$p->name]['type'] . '<sup>*</sup>';
						else $typ = 'mixed';
						
					 	$l[] = '<span style="">' . $typ . ' ' . $ay  . '</span>';
					 	$cType = str_replace('<sup>*</sup>', null, '<span style="color:#1c6;">' . $typ . ' ' . $ay . '</span>');
					}
					
					if ($p->isOptional())  
					{
						$b1 = '[' ; 
						$b2 = ']';
						$bl2 = '<span style="font-style:italic">&nbsp;(optional)</span>' ; 
						$bl1 = '';
					}
					else  $b1 = $b2 = null;
					
					$l[] = $bl1 . $p->name . $bl2;
					$c[] = $b1 . ($first ? null : ', ') . $cType . $p->name ;
					if ($b2 == ']') ++$ic;
					
					if ($p->isDefaultValueAvailable())
					{
						$q = '`';
						$qc = '\'';
						$dv = $p->getDefaultValue();
						if (is_array($dv))
						{
							$t = array();
							$s = array();
							foreach($dv as $k=>$v)
							{
								if (is_null($v)) $v = 'NULL';
								if (is_bool($v)) $v = ($v ? 'TRUE' : 'FALSE');
								$t[] = $k . '=>' . $v;
							}
							$dv = 'ARRAY (' . implode(', ', $t) . ')';
							$q = null;
						}
	
						if (is_bool($dv))
						{
							$dv = $dv ? 'TRUE' : 'FALSE';
							$q = $qc = null;
						}
	
//						$l[] = '(Default = ' . $q . $dv . $q . ')';
						$l[] = $q . $dv . $q;
						$c[] = ' = ' . $qc . $dv . $qc ;
					}
					
					if (isset($disectedMethodBlock['parameters'][$methodName][$p->name]['notes'])) $note =  $disectedMethodBlock['parameters'][$methodName][$p->name]['notes'];
					else  $note = null;

					$ll[] =  '<tr><td>'. $l[0] . '</td><td>'. $l[1] . '</td><td>'. (isset($l[2]) ? $l[2] : '&nbsp;') . '</td><td>' . $note . '</td><tr>' . "\n";
					$cc .= implode(' ', $c) .  ' ';
					$first = false;
				}
				
				if (isset($disectedMethodBlock['return']['type'])) $ret = $disectedMethodBlock['return']['type'];
				else  $ret = 'mixed';
				
				$ret = '<span style="color:#1c6;"> ' . $ret . ' </span>';

				echo '<div style="padding: 3ex 2em 0; font-weight: bold;">' . $ret . $methodName . ' (' . $cc . ($ic != 0 ? str_repeat('] ', $ic) : null) . ')</div>';

				if (isset($disectedMethodBlock['return']['notes'])) $ret = $disectedMethodBlock['return']['notes'];
				else  $ret = null;
				
				if (!empty($ret)) echo '<dir style="padding: 0 2em; font-weight: bold;">Return Value: ' . $ret . ' </dir><br/>' . "\n";

				echo '<hr style="width: 75%; border: 1px solid #ddd;" />';
				
				echo "<h4>Method Parameters:</h4><blockquote>" . "\n";
				
				echo '<table style="border: 0; width: 80%; margin: 0 auto 0 2em; background-color: white;">';
				echo '<tr style="background-color:#eee; font-weight: bold;"><th>Return Value</th><th>Parameter</th><th>Default value</th><th>Notes</th>';
				echo implode("\n", $ll) . '<br/>';
				echo '</table>';
				echo "</blockquote>" . "\n";
			}
					
			$code = array();
	
	// vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
	//
	// ... The @document tag in the DocBlock is custom for this code
	// ... The syntax is "@document var1 [[[var2] [var3] .... ]
	// ... Where var1, var2, etc are the names of variables that should be documented when in the render
	//			
			$documentVarList = array();
			if (!empty($docMethod))
			{
	// 
	//	... Collect the list of variables that need to be documented
	//
				if (($s = stripos($docMethod, '@document ')) !== false)
				{
					$rem = substr($docMethod, $s);
					$l = explode("\n", $rem);
					foreach($l as $i=>$line)
					{
						if (stripos($line, '@document ') !== false) 
						{
							list(,$dv) = explode('@document', $line);
							list($dv, $v, $dd) = explode(' ', $line, 3);
							$documentVarList[$v] = $dd;
						}
					}
				} 
				
	//
	// ... If variables need to be documented then get the source code and read the variable definitions
	//					
				if (count($documentVarList) > 0) 
				{
					echo "<h4>Method Variable Definitions:</h4><blockquote>" . "\n";
				
					if (count($code) == 0) $code = $this->getThisCode();
					else reset($code);
	
					$methodFound = false;	
					foreach ($code as $line)
					{
						if (strpos($line, $m->name) === false ||
							stripos($line, implode('|', $this->thisClassMethodModifiers[$methodName])) === false) continue;
					}
					
					if (count($documentVarList) > 0 )
					{
						$methodSource = $this->getThisCodeMethod($methodName);
	
						foreach ($documentVarList as $var=>$def)
						{
							$varDef = $this->getVariableDefinition($var, $def, $methodSource, 'table');
							echo $varDef;
						}
						
					}
					else
					{
					}
	
					echo "</blockquote>" . "\n";
				
				}
			}
	// ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
			echo '</div>' . "\n";

		}
		echo '</div>' . "\n";
	}
	
} // function end



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * findSet
 *
 * Finds the first string that is properly nested using a matching pair of delimiters. The 
 * delimiters are () {} <> & {}
 * 
 * @param string $string      String in which to find set
 * @param string $chrOpen     The opening character of the set delimiter '(' | '[' | '<' | '{'
 * @param bool   $includeEnds If TRUE, the returned string will include the leading and ending delimeters
 * @param mixed  $details     Returns the various values that describe the where the set is
 *
 * @document details The indices of the array that gets assigned to $details
 *
 * @return string|bool Value within the set or FALSE 
 *
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
public function findSet($string, $chrOpen='(', $includeEnds=true, &$details=array())
{
	$details = array('originalLength'=>false, 'setStart'=>false,  'setEnd'=>false, 'setLength'=>false, 'includeEnds'=>$includeEnds);
	$chrSets = array('('=>')', '['=>']', '<'=>'>',  '{'=>'}', );
	$pos = -1;
	$cnt = 0;  
	
	$chrs = str_split($string, 1);

	$details['originalLength'] = count($chrs);
	
	$final = null;
	$store = false;
	foreach ($chrs as $c)
	{
		++$pos;
		
		if ($c == $chrOpen) 
		{
			if ($cnt == 0) 
			{
				$store = true;
				if ($includeEnds) 
				{	
					$final .= $chrOpen;
					$details['setStart'] = $pos;
				}
				else $details['setStart'] = $pos + 1;

			}
			else  $final .= $c;
			
			++$cnt;
			continue;
		}
		else if ($c == $chrSets[$chrOpen]) 
		{
			--$cnt;
			if ($cnt == 0)
			{
				$store = false;
				if ($includeEnds) 
				{
					$final .= $c;
					$details['setEnd'] = $pos;
				}
				else $details['setEnd'] = $pos - 1;
				break;
			}
			else $final .= $c;
			continue;
		}
		else if ($store) $final .= $c;
	}
	
	$details['setLength'] = strlen($final);
	
	if ($cnt == 0) return $final;
	else return false;
	
}



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * getThisClassMethod
 * 
 * Returns the ReflectionMethod object for the requested method. Used to get method parameters, etc. 
 * 
 * @param string $method Name of class method
 *
 * @return ReflectionMethod|bool Returns FALSE if the requested method does not exist in class
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
protected function getThisClassMethod($method)
{
	if (!empty($this->thisClassMethods) && !isset($this->thisClassMethods[$method])) return false;
	
	if (empty($this->thisClassMethods)) $this->loadClass(); 
	
	if (!isset($this->thisClassMethods[$method])) return false;
	else return $this->thisClassMethods[$method];
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 *  getThisCode
 * 
 * Returns the source code for this class. The source is stored as the class variable $thisCode.
 * 
 * @param bool $force If TRUE then the source code is read from disk, regardless of whether it was previously loaded. Default = FALSE.
 *
 * @return string Returns NULL if no source is found.
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
protected function getThisCode($force=false)
{
	if (!isset($this->thisCode) || empty($this->thisCode) || $force)
	{
		if (!isset($this->thisClass) || empty($this->thisClass) || !is_a($this->thisClass, 'ReflectionClass')) return false;
		
		$this->thisCode = file($this->thisClass->getFileName());
		
		return $this->thisCode;
	}
	else return $this->thisCode;
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 *  getThisCodeMethod
 * 
 * Returns the source code for the requested method within this class. The source is stored in an 
 * element in the class variable array $thisCodeMethods[]. The index into $thisCodeMethods[] is the method name.
 * 
 * @param string $method Name of method 
 *
 * @return string Returns NULL if no source is found.
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
protected function getThisCodeMethod($method)
{
	if (isset($this->thisCodeMethods[$method])) return $this->thisCodeMethods[$method];
	
	$code = $this->getThisCode();

	$store = false;
	$funcStart = 0;
	$funcSource = null;
	foreach ($code as $idx=>$line)
	{
		if ( ($lf = stripos($line, 'function')) && ($lm = stripos($line, $method)) && $lf < $lm )
		{
			$startIndex = $idx;
			$search = substr(implode(null, $code), $funcStart);
			
			$funcSource = $this->findSet($search, '{', false, $details);
		}
		$funcStart += strlen($line);
	}

	$this->thisCodeMethods[$method] = $funcSource;
	return $funcSource;
   }


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * getVariableDefinition
 *
 * Returns the definion (assignment) of the provided variable (variable name), within the provided method source code. 
 * This method will return an error (FALSE) if the variable is not found is the source code or 
 * if the variable is not an array.
 *
 * @param string $var
 * @param string $methodSource
 * @param string $format Accepted values are 'string' | 'table'
 * 
 * @return string|bool Returns FALSE in case of error
 *
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
protected function getVariableDefinition($var, $definition, &$methodSource=null, $format='table')
{
	if (empty($var) || empty($methodSource)) return false;
	
	if (substr($var,0, 1) != '$') $var = trim('$' . $var);
	
	$eval = null;
	$loc = strpos($methodSource, $var);
	
	if (!$loc) return false;
	
	$def = 	$this->findSet(substr($methodSource, $loc), '(', false);
	
	switch (strtolower($format))
	{
		case ('string'):
			$eval = $var . ' =  array [ ' . $def 	. ' ]';
			break;
			
		case ('table'):
			$eval .= '<table  class="shadow"  style="border: 1px solid #ddd; width: 500px; margin: 2em 0 auto 0;">';
			$eval .= "<tr ><th colspan='3' style='background-color:#ddd; font-weight:bold;'>Variable: {$var}</th></tr>";
			if (!empty($definition)) $eval .= "<tr style='border-bottom: 1px solid black;'><th colspan='3' style='background-color:#ddd; font-weight:bold;'>{$definition}</th></tr>";
			
			$tuples = explode(',', $def);
			$cnt = count($tuples);
			foreach ($tuples as $ix=>$line)
			{

				if ($ix+1 == $cnt || (isset($tuples[$ix+1]) && empty($tuples[$ix+1]))) $thick = 0;
				else $thick = 1;

	
			
				if (strpos($line, '=>')) 
				{
					list($key, $val) = explode('=>', $line);
					$eval .= '<tr  style="background-color: #efe; border-bottom: '.$thick.'px dashed #ddd;"><td>\'' . trim(str_replace(array('"', '\''), null, $key)) . '\'</td><td>=&gt;</td><td>' . str_replace(array('"', '\''), null, $val) . '</td></tr>';
				}
				else $eval .= '<tr  style="background-color: #efe; border-bottom: '.$thick.'px dashed #ddd;"><td colspan="3">' . str_replace(array('"', '\''), null, $line) . '</td></tr>';
			}				
			
			$eval .= "</table>\n";
			break;
	}

	return $eval;		
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 *  loadClass
 *
 * Collects and stores various values about 'this' class. The values are stored as class variables and 
 * are subsequently used by the document method.
 *
 * @return ReflectionClass 
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
public function loadClass($class=false)
{
	if (!$class) $class = $this->class;
	else $this->class = $class;
	if (!is_object($class)) return false;
	
	$this->thisClass = new ReflectionClass($class);
	$this->thisClassMethods = $this->thisClass->getMethods();
	$this->thisClassInterfaces = $this->thisClass->getInterfaces();
	$this->thisClassParent = $this->thisClass->getParentClass();
	$this->thisClassNamespaceName = $this->thisClass->getNamespaceName();


//
// ... Refactor array to use method name as index
//
	foreach($this->thisClassMethods as $idx=>$method)
	{
		$methodName = $method->name;
		if ($idx !== $method->getName())
		{
			$this->thisClassMethods[$methodName] = $method;
			unset($this->thisClassMethods[$idx]);
		}

		$mods = $mods2 = array();

//
// ... Determine if various characteristics of the requested method 
//
		if (method_exists($method, 'isFinal')      && $method->isFinal())     $mods[] = 'Final';
		if (method_exists($method, 'isPrivate')    && $method->isPrivate())   $mods[] = 'Private';
		if (method_exists($method, 'isProtected')  && $method->isProtected()) $mods[] = 'Protected';
		if (method_exists($method, 'isPublic')     && $method->isPublic())    $mods[] = 'Public';
		if (method_exists($method, 'isStatic')     && $method->isStatic())    $mods[] = 'Static';

		if ($method->isAbstract()) 	  $mods2[] = 'Abstract';
		if ($method->isConstructor()) $mods2[] = 'Constructor';
		if ($method->isDestructor())  $mods2[] = 'Destructor';

		$this->thisClassMethodModifiers[$methodName]  = $mods;
		$this->thisClassMethodModifiers2[$methodName]  = $mods2;
		$this->thisClassMethodParameters[$methodName] = $method->getParameters();
		
//
// ... Determine if various characteristics of the requested method's parameters 
//
		$pars = array();
		foreach ($this->thisClassMethodParameters[$methodName] as $p)
		{
			if (empty($p)) continue;
			
			$pName = $p->getName();

			$pars = array();

			if (method_exists($p, 'isArray')                 && $p->isArray())                 $pars[] = 'Array';
			if (method_exists($p, 'isCallable')              && $p->isCallable())              $pars[] = 'Callable';
			if (method_exists($p, 'isDefaultValueAvailable') && $p->isDefaultValueAvailable()) $pars[] = 'Default_Val_Available';
			if (method_exists($p, 'isOptional')              && $p->isOptional())              $pars[] = 'Optional';
			if (method_exists($p, 'isPassedByReference')     && $p->isPassedByReference())     $pars[] = 'Passed_by_Ref';

//				if (method_exists($p, 'isDefaultValueConstant')  && @$p->isDefaultValueConstant())  $pars[] = 'Default_Val_Cconstant'; // need PHP 5.4.6 or later  - commented out because of a quirk of the vsm installation
			if (method_exists($p, 'isVariadic')              && $p->isVariadic() )             $pars[] = 'Variadic';              // needs PHP 5.6 or later

			if (in_array('Default_Val_Available', $pars)) 
			{
				$tmp = $p->getDefaultValue();
				if (is_bool($tmp) && $tmp) $tmp = 'TRUE';
				if (is_bool($tmp) && !$tmp) $tmp = 'FALSE';
				
				if (is_array($tmp) && count($tmp) == 0) $tmp = 'ARRAY()';
				if (is_array($tmp) ) $tmp = 'ARRAY(' . implode(', ', $tmp) . ')';
				$pars[] = " (Default = " . $tmp . ")";
			}
			$this->thisClassMethodParameters[$methodName][$pName] = $pars;
		}

//
// ... Get the method's Doc Block 
//
		$this->thisClassMethodDocBlock[$methodName]   = $method->getDocComment();

	}

	return $this->thisClass;
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * remove_doublewhitespace
 */ 
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
static function remove_doublewhitespace($s = null)
{
	return  $ret = preg_replace('/([\s])\1+/', ' ', $s);
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * remove_whitespace
 */ 
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
static function remove_whitespace($s = null)
{
	return $ret = preg_replace('/[\s]+/', '', $s );
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * remove_whitespace_feed
 *
 */ 
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
static function remove_whitespace_feed( $s = null)
{
	return $ret = preg_replace('/[\t\n\r\0\x0B]/', '', $s);
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 *  WholeDirTree 
 *
 * Returns the directory tree topped by provided directory.
 *
 * @param string $root The top of the directory tree to return
 * @param string-array $extList List of file extensions that should be returned. If empty, then return all files
 * @param string-array $skipDir List of directories to exclude. Subdirectories of a excluded directory will also be excluded
 *
 * @return multi-level array [directory][] = filename  
 * 
 * @author Marc Zev
 *
 * @version 2.0.0.2015
 */
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
static public function  wholeDirTree ($root, $extList=array(), $skipDir=array())
{
	$paths = array();
	$extFlag = (count($extList) > 0) ? true : false;
	
	$rdi = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS );
	$iter = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST
												| RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
	);

	$paths = array($root);
	foreach ($iter as $path => $dir) 
	{
		if ($extFlag && !in_array($dir->getExtension(), $extList) ) continue;
		
		$d = $dir->getPath();
		$skipIt = false;
		foreach (explode('/', $d) as $v)
		{
			if (in_array($v, $skipDir)) $skipIt = true;
		}
		if ($skipIt) continue;
		$f = $dir->getFilename();
		
		if (!isset($paths[$d])) $paths[$d] = array();
		if (!in_array($f, $skipDir)) $paths[$d][] = $f . (($dir->isDir()) ? '/' : null);
	}

	ksort($paths);
	return ($paths);
}



};
