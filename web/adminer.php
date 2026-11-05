<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.3.0
*/namespace
Adminer;
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';

if (!isset($_SESSION['admin'])){
    http_response_code(401);
    header('Location: /');
    exit;
}
if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];const
VERSION="6.3.0";error_reporting(24575);set_error_handler(function($cd,$fd){return!!preg_match('~^Undefined (array key|offset|index)~',$fd);},E_WARNING|E_NOTICE);if(!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"))||ini_get("filter.default_flags")){foreach(['_GET','_POST','_COOKIE','_SERVER']as$X){$Pl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Pl)$$X=$Pl;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($Bb=null){return($Bb?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Pb=adminer()->credentials();$I=Driver::connect($Pb[0],$Pb[1],$Pb[2]);return(is_object($I)?$I:null);}function
idf_unescape($s){if(!preg_match('~^[`\'"[]~',$s))return$s;$Gf=substr($s,-1);return
str_replace($Gf.$Gf,$Gf,substr($s,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($za,$v,$h=null){return($za&&array_key_exists($v,$za)?$za[$v]:$h);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
int_type(){return'(tiny|small|medium|big)?int(eger|\d)?';}function
number_type(){return'(^('.int_type().'|decimal|numeric|number|real|(binary_|half_|scaled_)?float\d?|(binary_)?double( precision)?|(small)?money)$)';}function
text_type(){return'char|text'.(JUSH=="sql"?'|enum|set':'');}function
is_user_type($U){return
in_array($U,idx(driver()->structuredTypes(),'User types',[]));}function
full_type_sql(array$j){$U=$j["type"];return(is_user_type($U)?idf_escape($U).substr($j["full_type"],strlen($U)):$j["full_type"]);}function
is_searchable(array$j,array$X){if(!isset($j["privileges"]["where"]))return
false;if(preg_match('~NULL$~',$X["op"]))return
true;$U=$j["type"];$Jj=$X["val"];$Pa='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Pa~",$U))return
false;if(preg_match(number_type(),$U)){$hh='-?\d+(\.\d+)?';$Jg='';if(preg_match('~IN$~',$X["op"])){$Jj=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$Jj);$Jg="( *, *$hh)*";}elseif($X["op"]=="BETWEEN")$Jg="\\s+AND\\s+$hh";return(bool)preg_match('~^'.$hh.$Jg.'$~i',$Jj);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$Jj);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$Jj);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$Jj);return
true;}function
bracket_escape($s,$Ia=false){static$yl=[':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5'];return
strtr($s,($Ia?array_flip($yl):$yl));}function
url_escape($Q){static$yl=[];if(!$yl){$yl=[' '=>'+'];foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$cb)$yl[$cb]=sprintf('%%%02X',ord($cb));for($q=0;$q<256;$q++){if($q<32||$q>126)$yl[chr($q)]=sprintf('%%%02X',$q);}}return
strtr((string)$Q,$yl);}function
min_version($om,$bg="",$Bb=null){$Bb=connection($Bb);$Zj=$Bb->server_info;if($bg&&preg_match('~([\d.]+)-MariaDB~',$Zj,$_)){$Zj=$_[1];$om=$bg;}return$om&&version_compare($Zj,$om)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($Ch,$Y){return(function_exists('ini_set')?\ini_set($Ch,$Y):false);}function
ini_bool($af){$X=ini_get($af);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($af){$X=ini_get($af);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($J,$Qh){$eg=(int)ini_get("max_input_vars");return($eg?(int)floor(($eg-$Qh)/$J):0);}function
max_input_vars_error(){$af="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$af = ".ini_get($af)."</b>");}function
sid(){static$I;if($I===null)$I=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$I;}function
set_password($nm,$N,$V,$E){$_SESSION["pwds"][$nm][$N][$V]=($_COOKIE["adminer_key"]&&is_string($E)?[encrypt_string($E,$_COOKIE["adminer_key"])]:$E);}function
get_password(){$I=get_session("pwds");if(is_array($I))$I=($_COOKIE["adminer_key"]?decrypt_string($I[0],$_COOKIE["adminer_key"]):false);return$I;}function
get_val($G,$j=0,$Ab=null){$Ab=connection($Ab);$H=$Ab->query($G);if(!is_object($H))return
false;$J=$H->fetch_row();return($J?$J[$j]:false);}function
get_vals($G,$c=0){$I=[];$H=connection()->query($G);if(is_object($H)){while($J=$H->fetch_row())$I[]=$J[$c];}return$I;}function
get_key_vals($G,$Bb=null,$ck=true){$Bb=connection($Bb);$I=[];$H=$Bb->query($G);if(is_object($H)){while($J=$H->fetch_row()){if($ck)$I[$J[0]]=$J[1];else$I[]=$J[0];}}return$I;}function
get_rows($G,$Bb=null,$i="<p class='error'>"){$Ab=connection($Bb);$I=[];$H=$Ab->query($G);if(is_object($H)){while($J=$H->fetch_assoc())$I[]=$J;}elseif(!$H&&!$Bb&&$i&&(defined('Adminer\PAGE_HEADER')||$i=="-- "))echo$i.adminer()->error()."\n";return$I;}function
unique_array($J,array$u){foreach($u
as$t){if(preg_match("~^(PRIMARY|UNIQUE)$~",$t["type"])&&!$t["partial"]&&!$t["invalid"]){$I=[];foreach($t["columns"]as$v){if(!isset($J[$v]))continue
2;$I[$v]=$J[$v];}return$I;}}}function
where_function($ae,$c,array$j){if($ae=="md5")return
driver()->md5($c,$j)?:$c;return(in_array($ae,driver()->functions)||in_array($ae,driver()->grouping)?apply_sql_function($ae,$c):$c);}function
where(array$Z,array$k=[]){$I=[];foreach((array)$Z["where"]as$v=>$X){$v=bracket_escape($v,true);$c=idf_escape($v);$j=idx($k,$v,[]);$Cd=$j["type"];$nf=$j&&(is_blob($j)||preg_match('~binary~',$Cd));$I[]=$c.($nf&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$Cd=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$j["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="pgsql"&&preg_match('~\.~',$Cd)?"::text = ".q($X):(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($Cd,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($j,q($X))))))));if(JUSH=="sql"&&preg_match('~char|text~',$Cd)&&preg_match("~[^ -@]~",$X))$I[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$v)$I[]=idf_escape($v)." IS NULL";foreach((array)$Z["col"]as$q=>$pb){$X=idx($Z["val"],$q);$I[]=where_function(idx($Z["fun"],$q),idf_escape($pb),idx($k,$pb,[])).($X!==null?" = ".q($X):" IS NULL");}return
implode(" AND ",$I);}function
where_columns(array$k){$I=[];foreach((array)$_GET["null"]as$v)$I[$v]=true;foreach(array_keys((array)$_GET["where"])as$v)$I[bracket_escape($v,true)]=true;foreach((array)$_GET["col"]as$pb)$I[$pb]=true;return
array_intersect_key($I,$k);}function
where_check($X,array$k=[]){parse_str($X,$fb);return
where($fb,$k);}function
where_link($q,$c,$Y,$_h="="){$xh=($Y!==null?$_h:"IS NULL");return"&where[$q][col]=".url_escape($c).($xh!=first(adminer()->operators())?"&where[$q][op]=".url_escape($xh):"")."&where[$q][val]=".url_escape($Y);}function
convert_fields(array$d,array$k,array$M=[]){$I="";foreach($d
as$v=>$X){if($M&&!in_array(idf_escape($v),$M))continue;$_a=convert_field($k[$v]);if($_a)$I
.=", $_a AS ".idf_escape($v);}return$I;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),[";"=>"%3B",","=>"%2C"]);}function
cookie($A,$Y,$Qf=2592000){header("Set-Cookie: $A=".rawurlencode($Y).($Qf?"; expires=".gmdate("D, d M Y H:i:s",time()+$Qf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($A=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
php_error_text($i){return(ini_bool("html_errors")?html_entity_decode(strip_tags($i)):$i);}function
php_errors($Ya){$ed=[];set_error_handler(function($cd,$i)use(&$ed){$ed[]=preg_replace('~^\w+\([^)]*\)( \[[^]]*])?:\s*~','',php_error_text($i));return
true;},E_WARNING|E_NOTICE);$Ya();restore_error_handler();return$ed;}function
get_url($Yl,$Gb){$ed=php_errors(function()use($Yl,$Gb,&$I,&$we){$http_response_header=null;$I=file_get_contents($Yl,false,$Gb);$we=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);});return[$I,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($we,0,''),$_)?$_[1]:''),(array)$we,($I===false?implode("\n",$ed):''),];}function
json_decode_exact($vf){$vf=str_replace(['\\\\','\\"'],['\\u005c','\\u0022'],$vf);$vf=preg_replace('~"(\\\\u0001[^"]*+")|"[^"]*+"(*SKIP)(*FAIL)~','"\\\\u0001$1',$vf);return
json_decode(preg_replace('~"[^"]*+"(*SKIP)(*FAIL)|-?\d[-+.\deE]*+~','"\\\\u0001$0"',$vf));}function
json_scalar($X){return(is_string($X)&&substr($X,0,1)=="\1"?substr($X,1):$X);}function
json_encode_exact($X,$Ld=0){$vf=str_replace(['\\\\','\\"'],["\2","\3"],json_encode($X,$Ld));$vf=preg_replace('~"\\\\u0001(-?\d[^"\\\\]*)"|(")\\\\u0001(\\\\u0001[^"]*+")|"[^"]*+"(*SKIP)(*FAIL)~','$1$2$3',$vf);return
str_replace(["\2","\3"],['\\\\','\\"'],$vf);}function
get_settings($Jb){parse_str($_COOKIE[$Jb],$dk);return$dk;}function
get_setting($v,$Jb="adminer_settings",$h=null){return
idx(get_settings($Jb),$v,$h);}function
save_settings(array$dk,$Jb="adminer_settings"){$Y=http_build_query($dk+get_settings($Jb));cookie($Jb,$Y);$_COOKIE[$Jb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&session_status()==PHP_SESSION_NONE)session_start();}function
stop_session($Od=false){$bm=ini_bool("session.use_cookies");if(!$bm||$Od){session_write_close();if($bm&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($v){return$_SESSION[$v][DRIVER][SERVER][$_GET["username"]];}function
set_session($v,$X){$_SESSION[$v][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($nm,$N,$V,$g=null){$Xl=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($g!==null?"db|":"").($nm=='mssql'||$nm=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Xl,$_);return"$_[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($nm!="server"||$N!=""?url_escape($nm)."=".url_escape($N)."&":"")."username=".url_escape($V).($g!=""?"&db=".url_escape($g):"").($_[2]?"&$_[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($z,$ug=null,$P=303){if($ug!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($z!==null?$z:$_SERVER["REQUEST_URI"]))][]=$ug;}if($z!==null){if($z=="")$z=".";header("Location: $z",true,$P);exit;}}function
query_redirect($G,$z,$ug,$aj=true,$md=true,$xd=false,$ll=""){if($md){$wk=microtime(true);$xd=!connection()->query($G);$ll=format_time($wk);}$qk=($G?adminer()->messageQuery($G,$ll,$xd):"");if($xd){adminer()->error
.=adminer()->error().$qk.script("messagesPrint();")."<br>";return
false;}if($aj)redirect($z,$ug.$qk);return
true;}class
Queries{static$queries=[];static$start=0;}function
remember_query($G){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$G:(preg_match('~;$~',$G)?"DELIMITER ;;\n$G;\nDELIMITER ":$G).";");}function
queries($G){remember_query($G);return
connection()->query($G);}function
apply_queries($G,array$T,$gd='Adminer\table'){foreach($T
as$R){if(!queries("$G ".$gd($R)))return
false;}return
true;}function
queries_redirect($z,$ug,$aj){$Si=implode("\n",Queries::$queries);$ll=format_time(Queries::$start);return
query_redirect($Si,$z,$ug,$aj,false,!$aj,$ll);}function
format_time($wk){return
sprintf('%.3f s',max(0,microtime(true)-$wk));}function
relative_uri($Xl=''){return
preg_replace_callback('~^[^?]*~',function($_){return
str_replace(":","%3A",$_[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($Xl?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($Vh=""){return
substr(preg_replace("~(?<=[?&])($Vh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($A,$ec=false){$Ed=$_FILES[$A];if(!$Ed)return
null;foreach($Ed
as$v=>$X)$Ed[$v]=(array)$X;$I=[];foreach($Ed["error"]as$v=>$i){if($i)return$i;$l=$Ed["name"][$v];$tl=$Ed["tmp_name"][$v];$Eb=file_get_contents($ec&&preg_match('~\.gz$~',$l)?"compress.zlib://$tl":$tl);if($ec){$wk=substr($Eb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$wk))$Eb=iconv("utf-16","utf-8",$Eb);elseif($wk=="\xEF\xBB\xBF")$Eb=substr($Eb,3);}$I[]=[$l,$Eb];}return$I;}function
get_file($v,$ec=false,$mc=""){$Hd=get_files($v,$ec);if(!is_array($Hd))return$Hd;$I='';foreach($Hd
as$Ed){$Eb=$Ed[1];$I
.=$Eb;if($mc)$I
.=(preg_match("($mc\\s*\$)",$Eb)?"":$mc)."\n\n";}return$I;}function
upload_error($i){$mg=($i==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($i?'Unable to upload a file.'.($mg?" ".sprintf('Maximum allowed file size is %sB.',$mg):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
utf8_length($X){return
strlen(preg_replace('~[\x80-\xBF]~','',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$_);$hk=strlen($_[3]);$I=number_format($X,0,".","");$I=preg_replace('~\B(?=(\d{'.(strlen($_[2])?:$hk).'})*\d{'.$hk.'}$)~',$_[1],$I);return
strtr($I,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$v){$X=idx($S,$v,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$wa=($v=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));$X=in_array($v,["Data_length","Index_length","Data_free"])?adminer()->formatSizeValue($X):format_number($X);return($wa?"~ ":"").$X;}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$yd=false){$I=table_status($R,$yd);return($I?reset($I):["Name"=>$R]);}function
column_foreign_keys($R){$I=[];foreach(adminer()->foreignKeys($R)as$m){foreach($m["source"]as$X)$I[$X][]=$m;}return$I;}function
fields_from_edit(){$I=[];foreach((array)$_POST["field_keys"]as$v=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$v];$_POST["fields"][$X]=$_POST["field_vals"][$v];}}foreach((array)$_POST["fields"]as$v=>$X){$A=bracket_escape($v,true);$I[$A]=["field"=>$A,"full_type"=>"","type"=>"","privileges"=>["insert"=>1,"update"=>1,"where"=>1,"order"=>1],"null"=>true,"auto_increment"=>($A==driver()->primary),];}return$I;}function
dump_headers($Je,$Mg=false){$I=adminer()->dumpHeaders($Je,$Mg);$Sh=$_POST["output"];if($Sh!="text"||$I=="tar"){$zb=($Sh!="text"&&$Sh!="file"&&preg_match('~^[0-9a-z]+$~',$Sh)?".$Sh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($Je).".$I$zb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$I;}function
dump_csv(array$J){$Fl=$_POST["format"]=="tsv";foreach($J
as$v=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($Fl?'\t':'[,;]|^$').'~',$X))$J[$v]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($Fl?"\t":";")),$J)."\r\n";}function
parse_csv($Sb,$Tj){$I=[];preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Sb,$cg);foreach($cg[0]as$J){preg_match_all("~((?>\"[^\"]*\")+|[^$Tj]*)$Tj~",$J.$Tj,$dg);$I[]=$dg[1];}return$I;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?idf_unescape($X):$X);}function
apply_sql_function($o,$c){return($o?($o=="unixepoch"?"DATETIME($c, '$o')":($o=="count distinct"?"COUNT(DISTINCT ":strtoupper("$o("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($l){if(is_link($l))return;$n=@fopen($l,"c+");if(!$n)return;@chmod($l,0660);if(!flock($n,LOCK_EX)){fclose($n);return;}return$n;}function
file_write_unlock($n,$Wb){rewind($n);fwrite($n,$Wb);ftruncate($n,strlen($Wb));file_unlock($n);}function
file_unlock($n){flock($n,LOCK_UN);fclose($n);}function
first(array$za){return
reset($za);}function
password_file($Mb){$l=get_temp_dir()."/adminer.key";if(!$Mb&&!file_exists($l))return'';$n=file_open_lock($l);if(!$n)return'';$I=stream_get_contents($n);if(!$I){$I=rand_string();file_write_unlock($n,$I);}else
file_unlock($n);return$I;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$y,array$j,$jl,array$pi=[]){if(is_array($X)){$I="";if(array_filter($X,'is_array')==array_values($X)){$_f=[];foreach($X
as$W)$_f+=array_fill_keys(array_keys($W),null);foreach(array_keys($_f)as$yf)$I
.="<th>".h($yf);foreach($X
as$W){$I
.="<tr>";foreach(array_merge($_f,$W)as$hm)$I
.="<td>".select_value($hm,$y,$j,$jl,$pi);}}else{foreach($X
as$yf=>$W)$I
.="<tr>".($X!=array_values($X)?"<th>".h($yf):"")."<td>".select_value($W,$y,$j,$jl,$pi);}return"<table>$I</table>";}if(!$y)$y=adminer()->selectLink($X,$j);if($y===null){if(is_mail($X))$y="mailto:$X";if(is_url($X))$y=$X;}$X=driver()->value($X,$j);$I=adminer()->editVal($X,$j);if($I!==null){if(!is_utf8($I))$I="\0";elseif($jl!=""&&is_shortable($j))$I=shorten_utf8($I,max(0,+$jl),"",$pi);else$I=highlight_matches($I,$pi);}return
adminer()->selectVal($I,$y,$j,$X);}function
is_blob(array$j){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$j["type"])&&!in_array($j["type"],idx(driver()->structuredTypes(),'User types',[]));}function
is_identity_always(array$j){return$j["auto_increment"]&&(JUSH=="mssql"||$j["default"]=="GENERATED ALWAYS AS IDENTITY");}function
is_mail($Rc){$Ba='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$Dc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$oi="$Ba+(\\.$Ba+)*@($Dc?\\.)+$Dc";return
is_string($Rc)&&preg_match("(^$oi(,\\s*$oi)*\$)i",$Rc);}function
is_url($Q){$Dc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($Dc?\\.)+$Dc(:\\d+)?(/\\S*)?(\\?\\S*)?(#\\S*)?\$~iD",$Q);}function
is_ipv6($ia){$p='[\da-f]{1,4}';$mf='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($p:){7}$p|($p:){6}$mf|(($p:)*$p)?::(($p:)*($p|$mf))?)$~iD",$ia);}function
is_shortable(array$j){return!preg_match('~'.number_type().'|date|time|year~',$j["type"]);}function
url_host($Fe){return(strpos($Fe,":")!==false?"[$Fe]":$Fe);}function
server_parts(array$ii){return["scheme"=>(string)$ii["scheme"],"host"=>(string)$ii["host"],"port"=>(string)$ii["port"],"socket"=>(string)$ii["socket"],"path"=>(string)$ii["path"],];}function
parse_server($N){if($N=="")return
server_parts([]);if($N[0]==":"&&!is_ipv6($N)){$nj=substr($N,1);if(preg_match('~^\d+$~D',$nj))return
server_parts(["port"=>$nj]);return(preg_match('~^/[-\w.:/]*$~D',$nj)?server_parts(["socket"=>$nj]):null);}$Hj="";if(preg_match('~^([-+.\w]+)://~',$N,$_)){$Hj=strtolower($_[1]);$N=substr($N,strlen($_[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$N,$_))return(is_ipv6($_[1])?server_parts(["scheme"=>$Hj,"host"=>$_[1],"port"=>$_[3],"path"=>$_[4]]):null);if(is_ipv6($N))return
server_parts(["scheme"=>$Hj,"host"=>$N]);if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$N,$_))return
server_parts(["scheme"=>$Hj,"host"=>$_[1],"port"=>$_[3]]);return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$N,$_)?server_parts(["scheme"=>$Hj,"host"=>$_[1],"port"=>$_[3],"path"=>$_[4]]):null);}function
count_rows($R,array$Z,$of,array$p){$G=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($of&&(JUSH=="sql"||count($p)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$p).")$G":"SELECT COUNT(*)".($of?" FROM (SELECT 1$G GROUP BY ".implode(", ",$p).") x":$G));}function
slow_query($G){$ik=driver()->slowQuery($G,adminer()->queryTimeout());ob_flush();flush();return@get_key_vals(($ik?:$G),null,false);}function
get_token(){$Xi=rand(1,1e6);return($Xi^$_SESSION["token"]).":$Xi";}function
verify_token(){return true;}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$sc=""){$sa=array_flip(str_split(compress_alphabet()));$w=strlen($Q);$jm=($w?13*($w-1)/2-$sa[$Q[0]]:0);$Pa="";$nj=0;$oj=0;for($q=1;$q<$w;$q+=2){$nj=($nj<<13)+$sa[$Q[$q]]*93+$sa[$Q[$q+1]];$oj+=13;while($oj>=8&&$jm>=8){$oj-=8;$jm-=8;$Pa
.=chr($nj>>$oj);$nj&=(1<<$oj)-1;}}if($Pa=="")return"";if($sc!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,['dictionary'=>$sc]),$Pa,ZLIB_FINISH);return($sc==""&&function_exists('gzinflate')?gzinflate($Pa):inflate($Pa,$sc));}function
inflate($Pa,$sc=""){$Nf=[3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258];$Of=[0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0];$wc=[1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577];$yc=[0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13];$I=$sc;$F=0;do{$Jd=inflate_bits($Pa,$F,1);$U=inflate_bits($Pa,$F,2);if(!$U){$F=($F+7)&~7;$w=inflate_bits($Pa,$F,16);$F+=16;$I
.=substr($Pa,$F>>3,$w);$F+=$w<<3;}else{if($U==1){$Wf=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$zc=array_fill(0,30,5);}else{$Vf=inflate_bits($Pa,$F,5)+257;$xc=inflate_bits($Pa,$F,5)+1;$C=[16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15];$Ag=array_fill(0,19,0);$_g=inflate_bits($Pa,$F,4)+4;for($q=0;$q<$_g;$q++)$Ag[$C[$q]]=inflate_bits($Pa,$F,3);$Bg=inflate_table($Ag);$Pf=[];while(count($Pf)<$Vf+$xc){$Kk=inflate_symbol($Pa,$F,$Bg);if($Kk==16)$Pf=array_merge($Pf,array_fill(0,inflate_bits($Pa,$F,2)+3,end($Pf)));elseif($Kk==17)$Pf=array_merge($Pf,array_fill(0,inflate_bits($Pa,$F,3)+3,0));elseif($Kk==18)$Pf=array_merge($Pf,array_fill(0,inflate_bits($Pa,$F,7)+11,0));else$Pf[]=$Kk;}$Wf=array_slice($Pf,0,$Vf);$zc=array_slice($Pf,$Vf);}$Xf=inflate_table($Wf);$Ac=inflate_table($zc);while(($Kk=inflate_symbol($Pa,$F,$Xf))!=256){if($Kk<256)$I
.=chr($Kk);else{$w=$Nf[$Kk-257]+inflate_bits($Pa,$F,$Of[$Kk-257]);$_c=inflate_symbol($Pa,$F,$Ac);$oh=strlen($I)-$wc[$_c]-inflate_bits($Pa,$F,$yc[$_c]);for($q=0;$q<$w;$q++)$I
.=$I[$oh+$q];}}}}while(!$Jd);return($sc==""?$I:substr($I,strlen($sc)));}function
inflate_bits($Pa,&$F,$Lb){$I=0;for($q=0;$q<$Lb;$q++){$I+=((ord($Pa[$F>>3])>>($F&7))&1)<<$q;$F++;}return$I;}function
inflate_table(array$Pf){$R=[];$ob=0;for($Qa=1;$Qa<=max($Pf);$Qa++){foreach($Pf
as$Kk=>$w){if($w==$Qa){$R[$Qa][$ob]=$Kk;$ob++;}}$ob<<=1;}return$R;}function
inflate_symbol($Pa,&$F,array$R){$ob=0;$Qa=0;do{$ob=($ob<<1)+inflate_bits($Pa,$F,1);$Qa++;}while(!isset($R[$Qa][$ob]));return$R[$Qa][$ob];}function
script($nk,$xl="\n"){return"<script".nonce().">$nk</script>$xl";}function
script_src($Yl,$ic=false){return"<script src='".h($Yl)."'".nonce().($ic?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($hd,$oe,$xa=null){$ya=[];foreach(array_slice(func_get_args(),2)as$X)$ya[]=json_encode($X,JSON_UNESCAPED_UNICODE);return" data-on$hd='".str_replace(['&','<',"'"],['&amp;','&lt;','&#039;'],"$oe(".implode(", ",$ya).")")."'";}function
input_hidden($A,$Y=""){return"<input type='hidden' name='".h($A)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(['&','<','"',"'","\0"],['&amp;','&lt;','&quot;','&#039;','&#0;'],$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($A,$Y,$hb,$Bf="",$b="",$nb="",$Df=""){$I="<input type='checkbox' name='$A' value='".h($Y)."'".($hb?" checked":"").($Bf==""&&$nb?" class='$nb'":"").($Df?" aria-labelledby='$Df'":"").$b.">";return($Bf!=""?"<label".($nb?" class='$nb'":"").">$I".h($Bf)."</label>":$I);}function
optionlist($B,$Qj=null,$cm=false){$I="";foreach($B
as$yf=>$W){$Fh=[$yf=>$W];if(is_array($W)){$I
.='<optgroup label="'.h($yf).'">';$Fh=$W;}foreach($Fh
as$v=>$X)$I
.='<option'.($cm||is_string($v)?' value="'.h($v).'"':'').($Qj!==null&&($cm||is_string($v)?(string)$v:$X)===$Qj?' selected':'').'>'.h($X);if(is_array($W))$I
.='</optgroup>';}return$I;}function
group_system(array$Ug,$Gj=false){$I=[];$Lk=[];foreach($Ug
as$A){if($Gj?driver()->isSystem(DB,$A):driver()->isSystem($A))$Lk[]=$A;else$I[]=$A;}if($Lk)$I[sprintf('System%s','')]=$Lk;return$I;}function
html_select($A,array$B,$Y="",$b="",$Df=""){static$Bf=0;$Cf="";if(!$Df&&substr($B[""],0,1)=="("){$Bf++;$Df="label-$Bf";$Cf="<option value='' id='$Df'>".h($B[""]);unset($B[""]);}return"<select name='".h($A)."'".($Df?" aria-labelledby='$Df'":"")."$b>".$Cf.optionlist($B,$Y)."</select>";}function
html_radios($A,array$B,$Y="",$Tj=""){$I="";foreach($B
as$v=>$X)$I
.="<label><input type='radio' name='".h($A)."' value='".h($v)."'".($v==$Y?" checked":"").">".h($X)."</label>$Tj";return$I;}function
confirm($ug=""){return
on('click','confirmClick',$ug?:'Are you sure?');}function
print_fieldset($r,$Mf,$rm=false){echo"<fieldset><legend>","<a href='#fieldset-$r' class='toggle'>$Mf</a>","</legend>","<div id='fieldset-$r'".($rm?"":" class='hidden'").">\n";}function
bold($Ra,$nb=""){return($Ra?" class='active $nb'":($nb?" class='$nb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($D){return
remove_from_uri("page|next").($D?"&page=$D".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($D,$Tb){return" ".($D==$Tb?($D?"<b>".($D+1)."</b>":$D+1):'<a href="'.h(pagination_href($D)).'">'.($D+1)."</a>");}function
hidden_fields(array$Ni,array$Ne=[],$Ei=''){$I=false;foreach($Ni
as$v=>$X){if(!in_array($v,$Ne)){if(is_array($X))hidden_fields($X,[],$v);else{$I=true;echo
input_hidden(($Ei?$Ei."[$v]":$v),$X);}}}return$I;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$Wl){$Wl=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($Wl?on('submit','uploadProgress',ME."upload=$Wl",SESSION_NAME."=$Wl"):"");}function
file_input($b,$nj=""){$gg="max_file_uploads";$hg=ini_get($gg);$mg="upload_max_filesize";$ng=ini_bytes($mg);$Ai=ini_bytes("post_max_size");if($Ai&&$Ai<$ng){$mg="post_max_size";$ng=$Ai;}$og=ini_get($mg);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$hg,sprintf('Increase %s.',"$gg = $hg"),$ng,sprintf('Increase %s.',"$mg = $og")).">$nj":'File uploads are disabled.');}function
enum_input($U,$b,array$j,$Y,$Uc=""){preg_match_all("~".driver()->enumLength."~",$j["length"],$cg);$Ei=($j["type"]=="enum"?"val-":"");$hb=(is_array($Y)?in_array("null",$Y):$Y===null);$I=($j["null"]&&$Ei?"<label><input type='$U'$b value='null'".($hb?" checked":"")."><i>$Uc</i></label>":"");foreach($cg[0]as$X){$X=stripcslashes(idf_unescape($X));$hb=(is_array($Y)?in_array($Ei.$X,$Y):$Y===$X);$I
.=" <label><input type='$U'$b value='".h($Ei.$X)."'".($hb?' checked':'').'>'.h(adminer()->editVal($X,$j)).'</label>';}return$I;}function
input(array$j,$Y,$o,$Ga=false,$Tl=false){$A=h(bracket_escape($j["field"]));echo"<td class='function'>";$bd=driver()->enumLength($j);if($bd){$j["type"]="enum";$j["length"]=$bd;}$B=($j["type"]=="enum"||$j["type"]=="set");if(is_array($Y)&&!$o&&!$B)$o="json";$vf=($o=="json"||preg_match('~^jsonb?$~',$j["full_type"]));if($vf&&$Y!=''&&(JUSH!="pgsql"||$j["type"]!="json")&&(is_array($Y)||!$_POST["save"])){$Ld=JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;$Y=(is_array($Y)?json_encode($Y,$Ld):json_encode_exact(json_decode_exact($Y),$Ld));}$mj=($Tl&&is_identity_always($j));if($mj&&!$_POST["save"])$o=null;$be=(isset($_GET["select"])||$mj?["orig"=>'original']:[])+adminer()->editFunctions($j);$b=" name='fields[$A]".($B?"[]":"")."'".($Ga?" autofocus":"");echo
driver()->unconvertFunction($j)." ";$R=$_GET["edit"]?:$_GET["select"];if($j["type"]=="enum")echo
h($be[""])."<td>".adminer()->editInput($R,$j,$b,$Y);else{$qe=(in_array($o,$be)||isset($be[$o]));$Kd=0;foreach($be
as$v=>$X){if($v===""||!$X)break;$Kd++;}echo(count($be)>1?"<select name='function[$A]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($be,$o===null||$qe?$o:"")."</select>":h(reset($be)))."<td".($Kd&&count($be)>1?on('input','skipOriginal',$Kd):"").">";$cf=adminer()->editInput($R,$j,$b,$Y);if($cf!="")echo$cf;elseif(preg_match('~bool~',$j["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($j["type"]=="set")echo
enum_input("checkbox",$b,$j,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($j)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$A'>";elseif($vf)echo"<textarea$b cols='50' rows='12' class='jush-json'>\n".h($Y).'</textarea>';elseif(($il=preg_match('~text|lob|memo~i',$j["type"]))||preg_match("~\n~",$Y)){if($il&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$K=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$K'";}echo"<textarea$b>\n".h($Y).'</textarea>';}else{$Jl=driver()->types();$Hl=$Jl[$j["type"]];$za=preg_match('~\[]~',$j["full_type"]);if($za)$pg=0;elseif(preg_match('~date|time|year~',$j["type"])){$Di=($j["length"]==""&&JUSH=="pgsql"?6:$j["length"]);$Vd=(preg_match('~time~',$j["type"])&&preg_match('~^[1-9]\d*$~',$Di)?$Di+1:0);$pg=($Hl?$Hl+$Vd:0);}elseif(!preg_match('~int|vector~',$j["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$j["length"],$_))$pg=(preg_match("~binary~",$j["type"])?2:1)*$_[1]+($_[3]?1:0)+($_[2]&&!$j["unsigned"]?1:0);else$pg=($Hl?$Hl+($j["unsigned"]?0:1):0);echo"<input".((!$qe||$o==="")&&preg_match('~^'.int_type().'$~',$j["type"])&&!$za?" type='number'":"")." value='".h($Y)."'".($pg?" data-maxlength='$pg'":"").(preg_match('~char|binary~',$j["type"])&&$pg>20?" size='".($pg>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$j,$Y),(count($be)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$j){$s=bracket_escape($j["field"]);$o=idx($_POST["function"],$s);if($o=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?idf_escape($j["field"]):false);if($o=="NULL")return"NULL";if(is_blob($j)&&ini_bool("file_uploads")){$Ed=get_file("fields-$s");if(!is_string($Ed))return
false;return(JUSH=="sql"?"_binary".q($Ed):driver()->quoteBinary($Ed));}$Y=idx($_POST["fields"],$s);if($Y===null)return
false;if($j["type"]=="enum"||driver()->enumLength($j)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($j["auto_increment"]&&$Y=="")return
null;if($j["type"]=="set")$Y=implode(",",(array)$Y);if($o=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($j,$Y,$o);}function
jump_input(){$Ef=['select'=>'select','insert'=>'insert','search'=>(support("table")?'Search data in tables':''),'schema'=>'Schema','database'=>'Database',];$I="<span id='jump'><input type='search' form='jump-form' name='filter' value='".h($_GET["filter"])."'"." placeholder='".'Search'."' aria-label='".'Search'."' autocomplete='off' autocapitalize='off'"." role='combobox' aria-expanded='false' aria-autocomplete='list' aria-controls='jump-list'".on('input','jumpInput').on('keydown','jumpKeydown');foreach($Ef
as$v=>$X)$I
.=($X!=""?" data-$v='".h($X)."'":"");return"$I><input type='submit' form='jump-form' value='".'Search'."' class='hidden'></span> ";}function
search_tables($G){$_GET["where"][0]["val"]=$G;$Sj="<ul>\n";foreach(table_status('',true)as$R=>$S){$A=adminer()->tableName($S);if(isset($S["Engine"])&&$A!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$H=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),[],$S)),1));if(!$H||$H->fetch_row()){$Ji="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$A</a>";echo"$Sj<li>".($H?$Ji:"<p class='error'>$Ji: ".adminer()->error())."\n";$Sj="";}}}echo($Sj?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($il,$gk=0){return
on('mouseover','helpMouseover',$il,$gk).on('mouseout','helpMouseout');}function
on_help_value($hj="",$lj=""){return
on('mouseover','helpValueMouseover',$hj,$lj).on('mouseout','helpMouseout');}function
edit_form($R,array$k,$J,$Tl,$i='',$G='',$ll=''){$Rk=adminer()->tableName(table_status1($R,true));page_header(($Tl?'Edit':'Insert'),$i,["select"=>[$R,$Rk]],$Rk);adminer()->editRowPrint($R,$k,$J,$Tl,$G,$ll);if($J===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form method='post' enctype='multipart/form-data' id='form'>\n";$Pc=false;$xm=($Tl&&!isset($_GET["select"])?where_columns($k):[]);$Hb=(count($xm)!=count($k));if(!$Hb)$xm=[];if(!$k)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Ga=!$_POST;foreach($k
as$A=>$j){echo"<tr".($xm[$A]?on('change','whereChange'):"")."><th>".adminer()->fieldName($j);$h=idx($_GET["set"],bracket_escape($A));if($h===null){$h=$j["default"];if($j["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$h,$jj))$h=$jj[1];if(JUSH=="sql"&&preg_match('~binary~',$j["type"]))$h=bin2hex($h);}$Y=($J!==null?($j["type"]=="set"&&is_array($J[$A])?implode(",",$J[$A]):(is_bool($J[$A])?+$J[$A]:$J[$A])):(!$Tl&&$j["auto_increment"]?"":(isset($_GET["select"])?false:$h)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$j);if(($Tl&&!isset($j["privileges"]["update"]))||$j["generated"])echo"<td class='function'><td>".select_value($Y,'',$j,null);else{$Pc=true;$o=($_POST["save"]?idx($_POST["function"],bracket_escape($A),""):($Tl&&preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Tl&&$Y==$j["default"]&&($j["default_sql"]||preg_match('~^[\w.]+\(~',$Y)))$o="SQL";if(preg_match("~time~",$j["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$o=(in_array("now",adminer()->editFunctions($j))?"now":"current_timestamp");}if(preg_match("~date~",$j["type"])&&preg_match('~^CURRENT_DATE~i',$Y)){$Y="";$o=(in_array("now",adminer()->editFunctions($j))?"now":"current_date");}if($j["type"]=="uuid"&&$Y=="uuid()"){$Y="";$o="uuid";}if($Ga!==false)$Ga=($j["auto_increment"]||$o=="now"||$o=="uuid"?null:true);input($j,$Y,$o,$Ga,$Tl);if($Ga)$Ga=false;}}if(driver()->primary!=""&&!fields($R))echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(["null"=>isset($_GET["select"])]))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Pc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Hb){$tc=($xm&&($i!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($Tl?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$tc".($Tl?on('click','ajaxForm','Saving…'):"").">\n";}}echo($Tl?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(["check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]]);echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($oi,$w){return
str_repeat("$oi{0,65535}",$w/65535)."$oi{0,".($w%65535)."}";}function
shorten_utf8($Q,$w=80,$Fk="",array$pi=[]){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$w).")($)?)u",$Q,$_))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$w).")($)?)",$Q,$_);$w=strlen(isset($_[2])?$_[1]:preg_replace('~\n[^\n]*\z~',"\n",$_[1]));return
highlight_matches($Q,$pi,$w).$Fk.(isset($_[2])?"":"<i>…</i>");}function
highlight_matches($Q,array$pi,$w=null){if($w===null)$w=strlen($Q);$I="";$F=0;if($pi&&@preg_match_all("((?|".implode("|",$pi)."))su",$Q,$cg,PREG_OFFSET_CAPTURE)){foreach($cg[0]as$_){list($il,$wk)=$_;if($il!=""&&$wk<$w){$Wc=min($wk+strlen($il),$w);$I
.=h(substr($Q,$F,$wk-$F))."<mark>".h(substr($Q,$wk,$Wc-$wk))."</mark>";$F=$Wc;}}}return$I.h(substr($Q,$F,$w-$F));}function
icon($Ie,$A,$He,$ol,$b=""){return"<button ".($A?"type='submit' name='$A'":"draggable='true' tabindex='-1'")." title='".h($ol)."' class='icon icon-$Ie".($A?"":" jsonly")."'$b><span>$He</span></button>";}function
copy_icon(){$Kb='Copy';return"<a href='' class='jsonly icon-copy' title='$Kb'><span>$Kb</span></a>";}if(isset($_GET["file"])){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string(')c(GViDWPFloQuDeeQUO^DgIF+y?4O<cX<jDcJA*[iuQ/YeF96jOuG0x[iu&n>{/Lt(peel""twnUSV4BjfRQHIaBSj#}4}^YKK<$BQMFP!+g0;Oaei]~TuoHWgJWsnlo=Q<zp
uKaG7hs1<!+Oi==O^}->/9Ea]~3synPV<Bm<c_L,Wm<:hI3>D]aslZ$SmG>H4Jg&]_G3uM_JP:Uxi;FAxIn_^J<D92mCE[jOq`wQHqqX[abvndl6
BuYT?K{gCgwCMO.[Bh>G.8O8j4cIh5,JaCXgWkoIQ!Xe%"4xCJ:#SsY.cQ)wF)[oAV@<X0jd2+]KMoD[>B4-v8{,,5LHUM34%n.ryR|:FR{0:aICtMI@=)$E^(E*:+o^~E%/JJVY<6`t<36)7mKyhI2:4Q%)Fo6^*oXn"r:NoSCF7=8WtpI91EqD&q]%mr`%zJG0Us@Lmi0lzD?Jm_.!%96.
05-6$9gJ5ax@d2@W#%4dOJ)dKrIhH<fII!d{G[[I[ajMju&sY5,h-_prPbj1%#EWXF1Gq.K+lD:sy4U_,<r&5^8e0eNI+ZclG?lfYe9UG!7:$;LSenK}0zA}Hr"r6Hk&7{RDK%2"Lqg8d<iC84Z1HzadoTQIv^Nm637v.T[>ekSylqlA2,Sd0UOW[GOnj]?I(rr3D?<fp#f77<J++3-&%V.~ZnmNJH[y]`-^0uG@G4iId}ogQQE
<?w%giV}f@vlXb
MvCbX8f:=d3Ax]qKEp-m^PAQ60knO9)+,(K`(k_*pRZtYK2oEe>T<T19
8/KE0QRqMQ2Zi{y|s_*Z*3nf*SKkX~pV^ZNE&eF+E=*;AA"3>99[b}$CN/W3C*?dXP.!6E:7&
8DN`6p1]rtfE4<_
f(P[Ux2ry0nf<PY]XTU^Ll=8vSj8kmWn;8oo8dfJYdbKvrr6d%L0F6vmh%?=RDtJ>od.Kf_;-K&lem4.[s6-U8?_qZ!30>e
#,<5F60=.DmmVj*!6Y,vLqPc2443S*-I32v==ZWdo4dR<xcq=.Tq,7iqik(&DOhm=Qoqt*5cDK;$y{"Bm
kF?)=ch!$5TIt8QN<9$a#R+(O1$s#aZNShtE-(.7_F``i:"}kxp]Yx6^OM^;e0M+??x79M7Y<Lg41Q@!!`Lo[[?s0%`hIXW7A?*rG&c)dt_!.k<P
!LH.#D0r[5j!nBvJFZ/
zca<%!yWK?IJCS-HJ[HNqf_!.pUz%_:@h&2bU2SnD&W"o>tH
SFubJ3(pod1+"-L.aeP6<C%N^VY:W1T:9YL&[C!X#~VLBaTF!aM@x"Jn8001@ofj_y.fRnEo1ooO_No)K-@D.)JN_QZ_@-sT5;N|.j%#r(hxf|D;JD6gJr<CqQY;E{2U=~eId)KBcs(+yT0AG0eX3;AyoGZwhlhi#X4G&W=<dBZR&)ef4xhwP:[9Fil*UQth<COFaql94jP34`J&C8]sY{C"^rVW_HOH.);{<5fuu&Y}TX?f(q#UcrQ!)3<7mnY=?5U@i!/D$K`n_rXT%vKD<dPBL6rr(n;?JKZH`1wlat.z
Y03*&^jP<h[>f)AYOQRdzD,NHD"(deXs0vAr*f>SWjb?2>=-2oqYE4&Zhc3i&9YW7i2xqjc%VA1,";Capf7<gW?OGIeLR`3<nPzQ(8=JL5w!xe.V/cY`mrrc}A6Fgcr&xo?&xe`6ljlw@SE_5wYVUy.&NfuXTfGCwJTe@Y/WAb8Z8/3Lx-=NNR)Y=o25@MDn8aS&"jKgv#2p_hBN3hAr%<Q`JWRR[$_C5-!!^4U@%kl=[#P9K,FIEY%EOt<5uRdQAZ]t%R<si6<O?wWEAEBIp($eb.HAC_>3a@yshOWmJQqipBLsG8~>`UTgfGN:mhdI<PLDzGUDLGlNV0hA`jKjhEpV1&bq7.!K)Nu?(9y%@Pe[
<(Gh8*RG8+k&wG+veu
^WerIBNNs76r#jb)_E+X*32W:U:C;LH1zcPJ?1YOKvWWz`bQH,/[+eF@mS/dvD}s5b~g<z$9=-2OaA%7
y[sakM[_h"r[F4`P(1cLxT*bQR6=%kJ`*6oM!OKX#!EX]QZI#Kt
s!o}vHn(C(^&ORC_h<IV`obwa|3p=F3(_bm~w4^:5xY!/k1GpX/vn=^W-{%
GaD:^K2-"5_"3b/ySXCP:|s
@FrGD*3U@=$Q=;lsPAH1tBP2oA70;4NeQOC+3@Cv"NM>.[hG"*F]PB8(/J0T81l2>*+XJ#qy,39V:%BQ=
w~7~!`O]bR>:?hLBCh>_5%S!.PS(0Y77oUkv00%mYYWbD-gEukB
RUv&iYZguO9%y@NcD[VnR>bAlY%F(4%K^U)3`-URp3[{no*.3,<S=4#c/`?v5~H,FHL|uAbdq.cwM,_=s[
:ct);`0B"xvqM?V_S+kl!2KQPpL<2Xp`&3pFXaV=RII(Z4GM<mY5-/2:SFUU*oUj%wF4@UK@i3_.OFgmNY0d~0Dv[<]0`q024;@%m=sg!/VP,b<G[o<;+n8@fGew.<gZ<Z#1OPEc{Y5i(I;<BAT9>gT`63MUwH(659gsG.dnuC1TU`/76G0rhbHodRo4,t>kG%,XA*1;neXg1w7))*bsl5*BMEGaaX)`3">,Y"?b><33K7gb-_n`)rn_qITk:GFFhgflkRq9qIr!@,/rOYBQ8i"Pv.b4]ASemkfhi>d:9_ihN:?x})m%9QgIA(BmAu1N8F#3H8R$U!GcERwKc4,v#C#Cg`nY&.ONKBXuolK]c]"l$#m-Et7N}B^@N6JcH1)Eb+3I-A$aP<Mxx1uU`[b#cH[1uxN8H4$mcifjxAXWI)8E[x:,Z+!HJ(K=jIDmZH_k4awgVS;["dZkEvG:G7QwF_egMN@DK1J,@f4dO3`Tpr09uH2M"ve.U8za~1dm]`[>XLzTf)k0KZmx27V
n^;m[an?.biQyvKlUxbAVa0FdXieVZ?9E2H$fuY6ma-s3b[_"326ck{nvuX_hMrf{Lsw
ZI5%v:M^S<rpc0,uI%e]F0clkXr)T>>E2!B$FKc$4`3PuwV5odughhv,7xlv]Z_P2$!6fmyihSd-K4J|u$;8Ejxc%JNF/&q_UUwdv,Y|dI3fbC_8buxQ8s3Vvvxo=AM:cpc<iJxcvdY`cRq$58E)]<jJZBOuO6__dG/&>c)AmSPkJmDf=DwEf_D@hiH~%_l8cPeCK+sRI]i2g*,Yh!B$tX2ia1/(iTek3P3^_8PSoH7q@2*.T0_xu)&}>CD55)EJ=.rXXzx0A}n<i70"Bh$uS(*{$8e5$x/Z1#lDnYR3C%a.lA9>?Y@B?8a,otCSGLjw,SnwbDy+&Auy58#|.fsEMOP-Erox.K_!:8:bU~(MGM;62!]a$j>j1>;^!7*/PY?Pc0<@W5>io>e-^0n{)Uq)h`gL%L=RM)IdGx"l5B*:ZFt]@&3Lo>`:=OiZ<w6}B:R"LFwB0[8jgwK<G}tmu"o&V%0~`[B`08yu8{wz6eR#b>e5f;u.s2KlMX8mgwn@iHocv]l=vygi]>/z8]ATOe>+",*hrjX.u8Q[B(=n]DC|6lEN8c9|n%J,[kJ~b?-sDJ]a#HL[VX%H)?O(FX=h`[Z+@Gqr$NG-*K=iO"p@!:,Ys<fl/&M,7e)9043fy,?*wg-(`<4aok0>nu>wL+=16U-_D?c(y2YX
;AfVIMj18WoK!a0HJR4cvQzYXijD@,oCeg5]9q9$kJi(Hhe-qO|-bvAu=uECp"M^g/jN1E7DvtyvYf@emMaUd$b/DhVnuhxi$xS?LL3-Z=}_|B``Yq+5qcGW;B%!7$S^=T8H3Y>OHC~qi1O+^ou6:EZWR<*HdExTUXCER(OG(l.AuO0p$3;Mv,fKzSL.)aL!d_RFLd+.$qprx#hZKS!^Eo%k_o
M;Cg/~F:
$.hx,o_DVla!~
~L!d|;6QUrukX!3_&*DOX7sc*ImaFKk,_+J$U_
@4vupHxO][ERVMahT6sm,,WN*$3ymI^f.**1Ne:Y5V;2m5ki&Lc1l[K&v_f1HA#:F.H3DJSqeh^=D0G@Ew:m9pW&>XdMwfp.yAm_l,+JT[;FJ[WyVi3u^S_v>s3!qg&eXk?Ka`c%^"*xC(6a*T^P7WTrc=e`uFkiw7A}fZ%=kqHz<+Lgi5stD%N8bphObsozc>y%/vAD1Hh=TlG%:E?rFoLw.[+DX2Jei"NvDAVH/QuzSXmCO)M&>"jztw8.y5hK:-_5]xq{rwnU[<&#aK8-j^P<0*]*7Fl>2!rH_WVG,c0KBxFiY[ha0b/y!b#d9;W3:,mL+d"j8_gdV>0GLHtDZy[-,M^3+Tm(+}g2R_Z(3xlhxA!ir|:U^O`UP?+wkn#z"U=M1"=3-3aQPDC>6k;mN+XC4`h6tK8hCJ+}0-u%`!*}V1RR#076"hR[(0x&;NtCJ^J<vQ+dV[J
W2`YynG1#O==l~)t&w@.*X28+-TV9C(8gh4Ie}kF"Ghs[5V8xg0#.!^Y3zE27qK5H"nO`_k8Y[<krL#]vvkC!$BFXZW0[e"Ha)+5dA:{Xw=J"!bJKWtx;D9|>,eo*u$UDkDEwd--DmJ(`5W<Qo-Q*0C]"P$H9JrsJIWKCO3s1;<aV_x;m6RwsZ=%$Cqub|p609udYkZ5!]DAGFK"_p*&1>X?G%jujw?_w/
g4vbfR*hZL-p1mZS=X~vKJ$=ho/_J
j*"V.eX*/B3Q)s,JU?Snr$r0y.}/=`a).n(;}Q~WY?/v`2AX^mm<QZt*g=.3N1m,.7=GB%DgeSx-QL)fpBX5cH9E;?9Vil^UrPT;qMkMxk:PtGe"<`1g{:LV9cKXLcmxE&=C)QnqgxX^i3Y-42-_/emXUHhkzdOr?!A@]Axrzvy23in;k>b1]3s5NxF=,7}U3xml=)yUu&OM(Bfxd');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('#OsbOb3V?!K0U*,j#-4P2Bz+35Y(O]|GIGFDsyf"RV_CWG&(<hG7poe1|](vZ=6gKb*-e*bfu`XNvNZUFTxP<kqOjr%%43Ge]*BE&fsS#$XGQs=jy?$Jqw)[=$pZs!#`BwhTS`Hx]c/
gWB0SBBXi)^FyF(MQLF,

CntAU.ADCkT^/<4hx@Sg^
Q:{DP1JO0$3OTgwkiNq>)k;xr&Qh*bS(@Bze[?PjZu!*h&UmI7<lxWc9)SaQvxR#;%$<pQAd+6,HWq@]vu3^lY1Y-Ns9y3L5H!=QP`I(rW5Hmx2spr4^XPbOa]@XKH,y^@Lq?=.-z7}qzeIF=B7yM-[7f*y?wDLK<WkFEA"t,#vW6>r<#Kf"]LY,kcXWxcX43
Ass`W9ARbvKD)]-@s[:$!rwYav_$?oR*(xrD_R;EA_gOpnVg]m(S7@|g#&qK8`wv8>Ve+uJAe<BCTZ=x251_N$KrVP!Q@9e&?&z={f4CNVgErN_i?URd=Xht|Qr-%P%uCSWYu<D86`]A3;1;d$x"Gm))J17Q
ZpWmu{G[:S:^$855HJcKZKE;S1-dlyr~:<jt
vN|yIqN`"?)$N>~l|SM3|;g7*iHMqkDXK,u=-Hto:2;IM[pN!44bS5#B},0<$3}je*,c@B7eav8x71@N{#iT(Z;#k)kUdlPj<1JMoyDm5@QZU5N:f5nPG96r~P]]M=:)mv=gMBhrpK&S2geLF@mf&uke2lkXlf8RiG~[JJkKov&yU$W
^-NPh_0Dd/,;3Lh5Xqqi+oH&|*hdQ%wk_U3(QJAG!:|Lxc<M4!tXBPGbzHyeN$$4U![h!BOH|/uZL0S@0?`M=qbV;Bk,}@3+n@Eq"FY;GKX`tx."s9E7uY,[6Rh;.v
(<8kBLH"Ke-/4^wv
dI2k`b#;@i+L}<oMML3kc
T*|N]?c(A]J!zj7Z6v?+[v"xR^qVr&HXOk_>=2=n10?w,o5J{Y;tk.Fnt^[!s$xmrAHN.V+S6?wVou8,Nt:OJ3#T6');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string(')`KbOh%Z51pK,ikTs?)"H;V,IIr:rC[t5,_F@ncm`@yoj3"_:"vA_VBRR$RI?[f_[w-[fNe2X4^yyl=Tq
x8x"wGoLxJJW6("@rvP__?ee$FXD
,"hNFt[01(c0@O1M
7cgR,9];2Vqh$;omxg<Z1+I`g40]
N"NMpph0J>.4woWEa9x,H~KyMz)^$~&=7y&,)3Zi/:#LuN9`cwOcNF<?p+mWOvqk^)lzHt%*ysfUs!_&74]IKI[=7l6yD%Ojl.PWvS,{1$@RW%HFpi8pXIotu
9vqP3l
9@k@b"CyS)^]UvEFq)f"lY^5p7"nPgWQ|pw)Me6V7oIeN2NFbY<NqbStJb7Y/
.1tWaHzr=-GDr&>Go;tQ.BK^T*:x^0PT1y1DsH*Ok=QJ*/~dv*qfQbO(X(XUDZ&"5,P2;yGI)3n2<9R_CsTh0T!%bPMYYTuaTDM0`+(IC/M>Ypv60-@gK7Nie
gFAOe]e__m8_~"l^9hki:Kc(#BF92R-4~W#A5_>QMyFx"hZln3g`T;WJ(ArvB=^V5?XpDYVi6mv_S=LnA8NT2=F=2o&v{X+mYJXnTc`STxNxcIF`+p1uuxaXiV;pE7Z7}5y.LFYknDJs1Vv7h_RQvkg#>M+odggKSiS2@[g/)6IBFW^%bi^PMv/l<MSO+1#T@&^*61^B"
7K2vVu=I.w#DxX|7Q%TUVc,CdL7;*;$(Xi?QbS2vl%+,e+Y,<goAui+:-iO-h/H;?1O/W%!AwZ7*&_y@U9:1otdYJ)5B};Kg&kZpbtxOm*ubdOP/awC!L)Kd<NJ[@d&rgb+(ojc^?S};RQvMZQ8qPeTUnE}3hA4WaX$Fb6[w7#j.|M^<&a8`(p2mPhBA?brn6`gKMc[:v=CJ]3aP`;")-8Xo^yiGyc]PGjFD`N~69p$di
LlR!SStMMcS>5&VM#cXL]FCfd
bNz/a,:Zr6([8BbJu1fjKHrj9Ea(;?qaiOr1^>nWp5)8e,{xGYpdr^%qs)
cLthctKR;6(sk5h$xbP)C]
@.:.J&$k;#GpyjqU?N1s>)=]GMKF-&+TQOhc,NAfX9N:&&2d)+CccK,KG`ptmluSbEVs+AQB#W1-c&*Ut/2m8#YSB]!#39x9TEM6RY
vqDY7Xw0wvfo9Y.nT9GwVR
%+UVSjIw$&PoJ7+5IYF:Qr+m2+:M`c<a6`AB!wpXBU8_5!^$-*6%i8:6$>)>mu4N9!Px
`Ga|m:>qTYNg9(WiXNAFl7lzYn97$]p{K,@*$WyQ,"#G=biZ[_g`wxNI`ACZm#
0!/$.$kG0pg`vU"y>KXxvwLDXT+&bTDeBWg5>j~]G,z@_AcBsWM"HW,=7rnR0TrYs4_HJCaye0FVbbAZS$
h6U"E-MAI
_GR1Olah,&WSed$fUtSWa%Osh@0)C8
iScU(]c?C<mdfbb9>+{8m:|Tz7ydRYzhX%5Q]XaMm(ypvytUeJ-n1K}yd#n(qJ]y^K{sd6km2=7eBLmPGeiK<al;LuBn}^X#Ip+IIlrV-Fn=jYG*sd$RvO]:[qQLeX~"i2E,,_mlwgPXkK*0EKkccF,P!bsOE"`XDEKGgATQ?9B1g9bJc
KyME)+LwPWk]SED>4oTJ6<%WlaYdOu2;I#aX#XStlD)=@>>USXpe7oCF/Tk@?6Oel0guR/k>]Q70<Y#45&5LE<>3f]#&U0?f!`/Xw%4
jDUJpWS>28V5T=M*~C9B?,Z(_&98ini6R$;@cSF"a)}sN2&c/Dfjb9GwNih<6!lhl-:[rF.cPo4&nP29{AI$m+r4J_B3HS#cQS{J=uehbK=b|.e;f3@6_IAGrGy+G:_pXFo`^_=WoPY>AOEW~/h8F/y]bQ&dc0HND-|UVbKjWeKq?dh+Z+(hh6Liiivq4H*i~Oob;sp"&`Reqy6%(eZIDv1ymiLP
vTh@ortB=K&}0ithe#BmfgXq(WjkX
G)*fd2f_fh@Pr=3PDhsda1iLIXM&ww16Q"G6gZ;YLjs>j?,.c~J8vma;6P)koE-)y]5rmv_+qsYk[Inzada/2JtMyc>fKIc2Kqy<Jl2VS?JoG&
!cpVIMP[~c^Gm1Fe>j6DN_Wdg$KGR*>5Z+_O?UXc_e)lmnL0t6}S~(A]qDR"t%eAXlM3>,zfJ0vZpp$_dZ,,zt..wTFU~!r"s8522AgB+>YDDI4T]lnolcFU;Lo]^;Sgp4Z(c$0oW=ZOP1PNvY1^?4q;l-ovUs<,1Y^8D%Rc)5dB!&9[.EeM^1mT1)Bg1/_jv<RllYu^]hurh)VlF>>ttiu!_&B2R-#(h%D#]Sw@g28+^i|"YB!_cBEa,fz)Ckf@&`ukf+#^x1MBv31,4d7D#yCOdVLZeq{<]2w*.+$m0r#3)wd)#<KJqOvs_73
<,?Pf]r2S]OK5;/-DX<u#
Uvc,ZX&DnNq(t2,F>#0H2ak.^"Rr=:&r~(4yuU_OIHq#YaS*=?c[dBn+
vpX3=v1I!Lg%k)tyCMRvJI;dxvmcC-M0/tJQkmlu!S>joCKgbh9v/iY@S
rB
R9p".6dPEe/KZsh8dC/C?S]5d2aR#d+`mrV1JYyx]6<vtS>lkU]j#t
j(vHx:=@HVX
/Fo@E#qrOe.ZD^"+$]DC#_&FHjMqN]yr$Tl"49j5e;A5A.V0P|ZiNbS>gW-`&PPr4H=Q8I&//7z"6sxM%_NYct,ofp*P#Lmi#j;__-wIqO=na1rrw~8q!5=m,0q$#M0,,X[W%/_HR#k6JTao6`wf@(
)z(UsmzkY_@LwUW$c:OQm9BorP1]/_y1,%yj>@RU*F}bN>e==
T)}S%^43Ilb73?E`1hGL`cWhPqH6I.GZ*CvI`/N0/i`)H9A#]05.F]A9Zxy[W"zTj#U8fI5P5UGUzSGNIN?.V
Rt537;Q-b=Lv>/f*$HQw;"DtT"pl7>.FpT}j%JVv1Cz-4<<hL#1o/)Mj3i;,qogAC=qUD
qn;Hnx(Pn^.nDht&*M&k^QM.rT
]aGhkf"`3)A8Yu2L*VF[R(nhV>=A.&giZ1rC<;l8v*Q=fn,IRr83MGO,-Rl<ThcvR;QSk(J--DWLFoyg-M#n?Z+kpoG<MsC^.s,(,Y=Zhew%(Aep^L4*39Cg3q<:2(>(k7O#
l%5*swkC<83^I6JVG
~wWJK5&x|d>3HnC"A^:wKRXbH;kWg/O6t6G4>sW:
vLL!W#D6_#CyQe+;$$JaCk<"0#RWu?mJ2elz9{U%lCxXpoM[q_lSsOwr<(yJsXlDN`nZ1%lIFW,9g%<"lv_if&xG`qdAcd.df<iI)H#~04XHr>NZ/Hdx!U1c"%y!RPXl1[3,btUAxpn3jyxiM3W,wJW7lE72G}8ZuZs7K)w)4k(%hXvN"RN9/Ts%hQ<~1t#O<>SpcG%Zd-ZRj"gFN:T:U=)W8i8omD0riCX7)>BJSsRV13%NT~,YYI"p$Vm@:3.>HiPv?C(]6#HCAky71lRN34kcb1ts3eCzEDY1=Ij*kDEU()rU/:WVj1WW821:g+h<G5cui=tO_~[XQ#nzRttfvJ55=wIflTW"6#&z)zCj(VCqR"VpNnR!b@Y_-<.Rr<M#NzSCx)"K8IA#+*Y6l},YeY_WB[[5Q5riJ4Ib*+Vhj(TI0,G$.xx*:t)*fbqsL0xyG`,]UlBPhm8;nZ;qpFz)t_tWw$M~H0feaG%961uRdKm.gPrPLT-
BIqbnzD<SPvMy{M,UJy~+|d!m"y<Yb
H.p%T^I==y
=@cSDmTo`NYgVi4FC[_j]v[C9kmgi`46.IiP8hQ^DH-mh?!]h=X"yUW=Mm`@5v*;IR(Lw3cse&l%Go=$tn0
n5Rxr>Sm#:[|;Sx>F$W
P4r9&RjKP2>6P~
7&Ni7^%aWmuamqU>]y7t[NBI*xZg,i#_v.J<$#gj3;"4/A0RAq5[#7+f%&H:(ww0;!
u(BS`7jmqF0ft99g=`&*?t)BZR`e+ru-f@"<c.o,QH<UDe0xbptroB92=sl";.yMrNJ(Y<WYf?Ns*FL|x6%?j`p~x~q*%"K{;"uTA",b,8>ah-`UiT1,E$iRiuDGQeqL)#;WVe%wlh!8fIH&Dz&Yh/$zG}_($Dv)nAJ@v/cu$"_;u/i~gKNhEf.x?:x3)q7)tgnP=2)xcBs"Dd)W2ycImsMuJ->$AUd6Sr)Z]MkL>>F*G[HK*=]"i{$tV0N->a`N9`jBMbVmhDhYNZ(?b^G9#7:~1
n#T~BMH^lJN^9rmJbMJaa!G}W~K$1zP<]cyHiUC22K[_I.G&X
K"v5Ha``!gqR_d=^s6Cup6rE?Fc~#">Boj^zcoh7,Tx%wq-B/m@(I=bu&Ia=O>e.Kye:CyoTwuv25$CE(wB;]]pemQnJ:rv{u9XVlsDmqxG_Uz]912RZn060Z3gEDyo
/oFfHjt-!vr8KwQ.2H
EIcG~i[y,)d:2R
XyWo<]2
W8tShKa"Y&<)c>yx1hu7C~@UQ#WC)u;dDl:/o*kGv|^A+_ecc|iK*<KTF,2$1bQ.D~b63bdRkE2Vh?I)>_-[Qo:fEcu$W=ugI9ZL"`C7H[uK!"pQ>3D5A/T}]te0F#f:_ry7M{.$`2:2b,lYEV+Tjk[[UXlY/8<rlPC{3+9&:t[mDiY=@H#A(AR>`_,([{xF8{=]R"IaP]!$AaX_*=I;3fL4Gp!=>.h_^y#*f!/"h9BlmE_)rXPf9NN3PL:L+mc/YH
M.8Z[d3;I@daqQRjzlQC3%TFVDe_IS|E"91nmaM"p8vsovQfyiQyB:G]_E:t[=p.>LR6T,
$6TPZS_h&>%Ar-J;C}[1&R)VsK-NbSHbcIA`QKBV1V<xR,c$bM-))wY[mEUc1:]tYW_~Lc.{9_f-;5A_l]fI-/WBy}4jnRa&Q-O_WnSQFD7,4!C*VNIu/d+W7%d/?l.]$3Xe!;W_7[E:-P";TG^%>ynRbUT5MIjsij@N$hv:
mGLo_.z
d$Wu;lZ2`P<7VgZ.HO-jF]()YTW9_N<*sPStyD{j#ffW-leZvZixA0a#4`If878O6]@&YSDK}x*pX1iO>g4ITAJ6$Oh<%XvmuSUdR=705F@T++eDa@ADKe$*93g.zkH`!a(GTQ`qD*6q~TO#8N!F|b=hhj/56CCObQG1H%Ew^v]>1D~#OE/7:UWX0Me2
_(jpZFk)V)Ge%ic7%
!)U0O/1"HS(_WWwzyCyE@shKq?!#M6`;
V2UajI-"rthK%/C-)OPo59BL3N#a}8ACJQT`V%f%C7@7|-%O[n+"ol7FZQKX3UE$)(O5B4eLy`{[+M+*82Q6?WBQxQ&epu(a{L7QYijjppkv1!FyUZ1.,A
/P+t7..)glu>R>[QQF3*`k
G>7Y5c%$?gvFedsFKrvE(O)OIXz_cdMfO(W=wjyCi
y[J^@&Pk.89C.^K[XORHia!0m.+o]XyRvfH%|$M/*srk/1lwyi|AaM$oe3#efkb2fcx9;q#n;])1nrm4?Kw@qY
n5(tnE=MUK%,B6)Wd}<XnDmT,6-GEX,TdqR%ON%4bov2jOz)mh81Vz4,s/)jG4^/Up
kc$UNEf]gqH6Qu~`s6!%VLsotnndz`-sbR?X^$,9S6[FDWIvelBQOVPsVaDEC&Phmoec4cJ5hkjqV.wI3-9/=sdE,;<YmW|#/<n*]g8PT+l$Wys[ryp97<{9kPG=m8V#&.NSOy4Aia17pXwa88bNcZ.G3[Np$,N;0w:/:+]*vUxW8al^jOr9+"
C}kZ6[kTa5s,I.2*&;hZG?p)p@*3z#dNDKUdSL%sb#:vS+aK/0mbv"]7.cn~`>lU+P/J93kn^&+@*5U(uJ@E#/<B/:g]G[YG*r_a7vwXB_gC5JrBasu44c/fJ+k1jC1BMH6K@=J2>)ERXd1Cf2nb;Y&-N;_KuQ?kRlmfFgfp`0RI<u(pW3eC-Qo>p5:fCo?Za~.U7[!4jg:f#XO`cY/:PGr/&_2eQ@xl)Cy5t*J&OP<2Vz
LIPD4$u@umM#X%msUlze32_+4mJ<{JN2,%-F)s%/
.{M]H-+~IZ2Yx;1"OJvOG:rVVMc
lz2+]vVAR*M}7eKJCi.lDn-#taG:oA[n;|fR101Yt-OYn8v=KuU5Uc]tj(J~9]l-V(&S1;D~=)XEcZQdkN>Q.gTe2GQ.U%)G=qW<G6!E`KIiU{;b["FF
b83)(qqju<`+vvaW/n;fb7p$w/MedWb?:6MY?20QefZXW*U?t5*(lr+wo45DPaayGX/[RiN6@w$=WoV7$HU)L-)g(n8I5*<N@o`eN*FjPGco*o<dvMfEKiJkrxU!J5VBR1hni,fjikn9}1^uhN^-N$mrwLO_-)LDJ-^Y._Y2BmurMGhPVh3]^ALQr(I6ym(`^Rjn$AOq4U|3`
X5G[1=~!cy"bMM$K@gD69jYZ89X3XbRRn4eSh,vxtw6*jR.o7<`@K7P=<iOWdK*In=9ZT.uI(5}M-$;km.*A(RbjRCc
7TM
N!b(A.uY2Bqip?;w8]ue/8
J!`kF
SYsUbIG^cu35F]%J!0S]!gtXv@vMGj
_=e"hQj:59QZ{xVWQ!~8Mn0/)N5/z8JqqnYqKUfA-p9
xUdX~W80=kE:Gh{=SQX_L(UOU/*Ko@*TOo0_!/!4pHzFqKuM*729fIk/B:7*TVsDTFOoBt7Fm^&Ox9ib4K.[U)0$|+gb-[M9Phgk,;eHZ4(^Htr@%m4r!nlWGw%A/5,6y29W*Qm)1dU)iF<_xTOZhI~3Y`2W{,^Y1$JZ"`nU0Q!!9?Nf*
f(jte7z`B%v71&Cx$0au@jPk5`%Lh*HFG,5]H(4.#@DVmmYO8RPd8-GS7fV)g39y3<(bc
`9))[DMEs_j&;=x@pyy7qnBDwSWBtJh<yoFVP,^m>ooq4,2JG3un9/SUS%5&12,pughmTDL.9YS(G`?nW&gO?t!lG(G<Z"pC;,:XW)biV"9!T7fFzU+OFYPVJ&/r5+*1D&2"U6d<d:s]aAv9oDCMJ2n>1TPWkw,^:^ldq!6UcOc?M!oR`.u%c]F$"u^s5NqEsKHV{p+9iX7_bR`*0"slbB.i>P
]INGG#qr6BK_FvCR+,w7;Q3~qQHP@3&wu!mmk2KE-q<##
xfJN^N_[`5m.RX&&,aQS)"D)L

mE9(T>.!~N:8>jo;r!N$h@%JveqX<r;;Hs<.m:)Ei<B<0aUfa
H-tmKRB)00VYp$y]zTHLW3(72O3;htH^4L^s/0z?fo@cwfd0;%_NyfbGCaPqbiVt269fPTWJbb)>HfxmWQ5F%KOYUY~JgEmMClo%U45kDpT#$BjFMT1ya1?H,;6NVTo)e0l4-8UJ+M5MQ(]dnU5cqKzu`OAU=nr2DK]0Wb7wkJN$:Y+vJ8,h&y:hyuQ8!T6M&9}1P5F+4_$LabuFd`+3@Gd$f]O#]O>_QDl[LVq4V-B^sEOs6;3n/8_R-;c#:waY;fw_NIx3{gy8wC!%Hm61Lj01k"RdK%w`?Jgm[RqYQm%49N%%
CA5#E<C]&`WbsNdH(dc]FmL[pK=^tq0AgvR?g*wSmD)$A3`P64JQ`kWrlr]N(cX~V>g#e#8g_DyZ4-ZV>f({3N)OjX!9jb!1whhj]F)7TT:4PVE^=BO&,<k^tIY2DFWjZjW;aP7k`n8W[9OTELeFI-@Sk$u:csqW?(vAQdvlLex=)`o;YssPN~@id/m|S?F0WvaZ/6@.0T&J?>X[^6Z9&!f?)LGb">.!=dqX5-)$T2Gm%~mffvdAyw/)NgQ]f/[w(L8V3-oF$J>;[YZn2(FD9oM$i[gQ-HS|p0ldH0`]vRVgUGt,)[Q0YpF<Y-c>(Q]]?gKgy01qxyj-aC*V;0fQ5~N))3poqN1
&@Tp?]Q>wBz#9~4_f%=>Lb.n8j:WO.j4:j2tJpN5mTUWS;:yk`/M(&.B:!U.nHA?-?Px8T2VVSGOTU4WPPi{p)AB/irBXn,Pu*"=U:t]T2M4(S+pobo%+l:]Sm
TZ5fCg-p/`!ftgHcA^MePfw)p_:i>?lU|?z0*>U=A[f9B[Rg`gYpVu{4%M&)Jt-j&Lue|W^%-@btFM6%(^VM)*3ayO[!{y5-#]|PMW-.8,/qDB`K~h*ek&hK;%?&KEn
5cVonyoiZaSMl6.=D0[B4cFXZ)jw0[Py@nOZ%.QYw
c=`K!Y&g}M`^JxP54!oon`>tyHaHGDS3@8Rx0q=%)EVR^w?@dt#2r=NA0r"p9Z<np8K;eZ.A)8X4~(M9.0Q^MH#Fuo->Hck#n;g+Kco:zUy)8Y(R!Al_%-CjK&Sn9VYv+?=!U`iuoH<gX=/C_!r2Vtv:_5^dS@BX"v)`5QJ/Z_39T>_A8+L`6+sDuv$;s$J%goJ>p)$"Ay@XHf0yvYXhlhb::i[-jqv9@M;Me:-2<;r9g#/YAr_%!h!dS
J
UdvCom3#Yn.i,vC/v<9p5G^lviKFcD`:m;ir8t/8ghk6KkClcA
3~Ef2=LCa9VqE@hRk*ejM(SoVTgT8VLLV1QQteGkDDXVE1<IB8dOhwU[JonSjl=e`#`MUcHt
CNa,331q84/`fG(S0&94VF-YVpspfQ46@cNmO^JR9I5abJM5YLMYzDf^Ixt[Jtm>e<A4_@F;$CHRC!l[mM!&~fW5.a
;yf>l%+sZND*M*?@d!/HVy8|FaRk#MT$e1u|w;?]D^$$t1MUjhUcMoaS=>s=[lUgl{RHlHkP!+v[N%M9]*UgE1mkB"H:=ZE94^f0&IR&?6Z`[xR)a39,MnA,2X4_G2kst:K7xnIb)alL&d6iQzJ{n:5ht;gl%lxM*l!dB:oXm`hCESUBb7_x`_42:]5!*dRx_y7|47n%XhFe:[88<H.!UjX<Dx>1E|GNYoj/(rA`FLK0GG=2aB_fU`EB&7_5t744t`mn6[fn`DVHPW:9m4u?X5k[5{t}qCIR1=Heah
`GN[oTM)UmnJdUuL49`F:dC*CF-B1b4Ta@00cT4DP!.>Yf(rELUw:w&x"lwiVyzu>s*
GKNZ3kQ,E@m>Yp<$0waa%yv?%J"k>Nu%y`f"epVvv#dbC*x5iTYP4$LFAC)qdtOX!W@(Mb?y%o*-#VK;sp^gpabmU]mBjt)G"+8G;g+ua,R],!HPnm<=%Jj.8Hy1p@xV2in")@&VUUvN-aX&N/KRN*m+PnQY{".Q3ub5[I$k<n8Z3`5a6clQNSK::+.l=yP1$ZrpD..]4$4#:cBDclJ
*=sEwX@Z~EU(*uNGhWGE*QT`USb.:d@3$jh[x-nrm#6]rg1n&X.XFc=9I*!g?Twcib/`4!57R2X[U3c*M!%=zvI6($hba@SrVQ;_vxA]O3G2*aXm:h3@I?,oN#CW7VGrlBzY&=Zs)TlJ}%0ws/8v}>bE*X!.LOli-FV2m4`${erjZ6lTXuh=<6j.Zy1baDXr8[N)E$
]iV;WM,<;9!EfnSi(di&:V$HIW_YZ`/6;hgM?S;bc#S;S[xWG}+-_*C]%t<~++k#t83urZ)m.Gmc-yw<1O^>#_*f)]aWX&<~8Gaz8V.AN+uSTis:sD(C7cC<On>:=}-tc2W$-;R$UBqHi$BSUV;D%B1#E3;;FDrEBf$.LVLA*QO`GBpp:gVk!}iZA:"j7BU1g@W=94c_&<r|uFalNWtrwe@+1-NiZ9$[+>&Wd!O[J&GSB@d(#rIc1+nu(@fZA9H^Arb6F|D/MuOx(o4<uT+@S#JMQV2RcQ.?Jc$Y1{@@_hLy%;]((zLu^vU}
i/X.prqYan{P5H&/8PV8%Y^[y#PS!#`Pvhy#(33T@,dVs[o(z]w/<JR4R2nY*EQcZ"Tm
UTZ
!X3%KMcpYJdydNJ*=j9hp&eH`m_?`t*4u+(>Hx":3yAby/??[s"=1k1,0)srNCkuGpO(b)e9Ew*k`3(`y*]R1ZhL[b
&R$pK$uP*z!z"W^P.Y4[.h.ehdhDppcf6wMEGgOnv3G/)s+&^HMI>:DucS++5Tk-5?7@Q?8uXWmwd=iL]@6P1UvbW_0!JhI(],ekPl-Zj;+)#B=CJ
C:{<RCU4:.ZLo1O3+d{#e`CQ[=jG{SM(oUu<3NCgb]<PMRi+:S?r.qe6+J#*Z>sPz62S]!T@_k"1`T;##@_3BMlQ+K#`Fy1MbQlR~ar``R*<Uy1nDAt0-sZI6H~qRDtF=dpQ_O;4Prcd0iU^"fU&JiR5<5$x}<a%)atw^pnw[0h:s<zH_d/Z]^"<Ak9>LI(BzCp8*!dF$Z6pR@G_eW3H2rr1
4(+KykywSK,aCd<x[$Ng/o8`gzn3,Gf;1P&%i@"=PF@NTDd37=vl=?QPq-)aNx!ni,S9Lc
z.}:j.o)X_[ox5DU5RzNt-!arq1?zXy?PPpW6?/W*,J!TYnf;dX]sXHsktonxC]inw,%4hB%jB@!}+A_m>I-UkJlw/~[E2#;h28h6"[3jo]8F*<(1B1vzbSfLq(tTEOOvL"qP.oJ>m(-JTidL]P(#TxC;%F=vbB1Fq}b<Z"l?_3Ehi<Q|y`EYKM&JdwS6fsjQBM0K2*P=A7bs=SsqPDkDiaT`#L&s/V]-7,a|2MHK
6VMs~$73!t3>x`UIfQcR,*m8M7~67+pN]fI&d_(eNx*O$!2[v]e0k6k*KDw+kj)P4#XsLGZ(NbeZc"TABFYAAWttjNSY;Z%Z/SVAd-0W`szQyk,ErgR!{>#^
UJ_JU~1G`p@h,[+02.+wLba/YXXStkVLf/KT76;q%UVX^J:^JX?m-wPylRU+QMTZmYA/$0uj&(S[U84_X=G(Lt=YY{ionXikhgW]D]fP6S!9^:#F=to&)0T`P$c;INZ0O3)@O?5dkOQ&jGc>2wfElU6RuIqoUVno5A+x84vNi2onF5x75{EPVtCtNWfBU@/NCgKju^0@<n((uQUB`0RQ0jV;V?S&Zr;J<&yx:bwP9Or_&E;Q@`u<sV`s7a:B&l-J-KXaUUv:*<mP5%1(7;6Q[Eo?,;c%!mw/a)*7D~QtaWXY4mg"h*_]fCk)L*BvLE]&57nO&`cJ"LTMul9Sk@5>VmQ
<-7zWRY~/$5q`%x=mD95ab;@0O;IY+y_]s)kf:v!%[a!vZ
0gurMH1Y_3}@YB"y.GJm@C4Cv(+0!E5*OL"+U$9+ke
BPUxP*E@<bUwaw2%ML$}Wzqxsl)JxS!7
7F-MyI#__cL*.ZYf_a%aQ)RYzY0YUyK8f?Nc=Hu/+E}KOE;#tR`k__AN]ci/+(4Q3d:TQop
pZvtyePMR#$khpr6kyzb[00mP27i?ENyZ4TO%l5m];Z^qdj6`,nZKNOY}*<ET!KxkNmv|`*mVwIL{`H3;;R2^Vav.e121=t(}yn86l8K
2|%H6-h~#T53,(DvFG_v!oUzimFC:d8Fy4d8GW$`].!Y?U-/%fe5)N:J?bS~Y)--[[qBZ^_4=sA^#Tsuh$LoP}P_k^IV
qll/`7<#SU"?c&p2).v]I&OmX9kY3Yl:|fWg"db5UEwR0@-WxqQ+Vq/NE1]Qqs,*duSD$OrZYJlwk=oZ#b#vbOn-OW+Fr+766(^CSXqAJ`q2Ub1_*aS?+Dzc.b}^<@mkrd!t[am2lE!QX@c`TC=X/3v.c^9l?JL"rKSWXEcADwKNgG,8uFY=8xjb9w!bbW$T#wPx1l;w8qkxPBq^AiRHVnDm-9+(z`_&Hd>yuwo!(DY(_:`jtDl^A"?E4IW7l!LKy5ck1@so_tEbtv4V7%x-GM|TWGf["S]Y{f"5w?V-:Qp
a;ZFPi1_8;H6d#}R+ue@hBeD"j#,um$ihp%]zx])+gpuG:|?[W^`ZPVB3ecqu;JKx^svjMHF;ix/@T(rJXJZUmTvxaJR1+Sf`s%N%0-H9mn5=ejYyRfw1l-Jai/m&fg[x_ghl8-()eY2bAE`r"<fa8dS+$lL2dpH&oy&)$<Bd24=O;j1`oRs!s9^p`c%(L~E[+0e9M}tW0nna<_&jEA[I!}`^@4,c&]P$2{FhX$46loh
^S=0^s?Oip2UMGSC`iKZ*cMLJ{>m@3P}U=AKEbF-y/ONGMJ?)Wg]/-#%t*)X0HHi:X&i,gyXPu/g:v-NI^#YFRFr$sTxPqMsNPo0e;
3Ppe_`g7A[^YqrH)jD$7?,ZNhph7U,(8?^,84XppH"v4KX0mh>o+bn)6}w4;Se3L<>zB{w6v
>tvB/|%L)XOuA+ZyJXP5>3OGQ^Cp#y#{aAUrS(!"v4CScC<-oZjK`rZMv~nAGVOO$ql-j8d(gQ
h9F)Ks<_ri21EVyrcaBWqmV*GklZaVfN;?YiVE@Unc,Ae)KA^`iuWLtKE1_<@oK;YTM1?YP3G
S$UqmdfFc&$5
W7nuX[d%$`[py<aF7
4^r)p7Ux=*SY)>-(EN+|D0LgV=u>[aLZifXf!OG-K?G,,a"Fy;q|RG!L]42HbK[;4R2:T87beViZa<T4r+"W!dsPmI-#MQasmLM)>8AzBN`18Fcpiwp@6[J,b
!s%wF"B&]U"U>NyWILqaFG1N"|g>)04Gu^>"n4QL;]C%_3KJ<bFzUJ*8fg=.]_4-15s;=,d-aPN`rU5VSs[YT{j;?~*prp+cE>;;a}]uP"]/1%KJ(N`[^lEDQ%Hm5FWan;0,tp[R`QLwA,]*o1B!!0LZ2de-Q`5!Z./Qebp-mPA(t)D$lmxkxVAA[>B},!y7+^&Yz%#Y"11p=i+MU?vj)q>v
jwrh2.&XAk%jBHam"1RRoQ!HTfXQn.HjEGW+Pk;m^"Pm4nun}QR4?Aey-<BqQD`F`k|h&Z,"Hyw&D9[`njW/aNby&*V<!I9;5qDaw_*$D2EI(XeH|1Zu5+n^/cLD.y|g(H-pXc|EOg;d-EvWS5&(GOh+=$p_qN7^f-2>hue]%JNZ}Pp&wA%JNGn
hcGFxervl%8OK@Gf=u`:#a3J+W/i|ksRWk-(}_&;Csrn{F+Wu=p;/qL>h4@>}:XQZ7mVp0sxw0Jg*O)NuR
Tx+H"bdSR<s{CTtz_i
:K
Jxc!+5jm*]+%2ExN`&GnI(fZ;5"A;-Et6;gQL.dEZ{rF57(k9!b^<MDp,hn}L3BP^RHAK
9xH3aixs<CtqLB7wt[&Kiab"u|IsY`*23"sJO&XhbwnVv?3l-]mzLuUJ5vbbOB_CHJnI9W+CahsMtc#*e7P4D>OEj23YJg!M)UyEfOAi.2"6
ECf48D(W"(eM4KMXk7&&6#"b|O[1N2t3/5OI6Gt4t>Y<:c2L16FUHsgZr/t1NB"y&-zIn
d"&f}p7VGVIJeG|rn@|/=
it[F#AGF#`aueAYkce@7Jt#:&voMLs
QB/Ekc]4A)*2MXvO,YK6UrG4J9A9M_.2TS"3?g8f`i2)U/_m"S`eMu+R
Q`x`W/)iA,|2p4dBiHOd1a;yvAEByiK5ZiJl=o0,z]AG?:~F!50V?_Qe_+N0z&P-PZlX{=<+)a:UU8FSv(unsc4M]k(,Ja]%3P0+[2
/oQOVC%WNzK^e|BmL/Y6Bq]iI_rhqlQS@6tAlzXEIxIC]19fN*llbkM0kVGr[?2=v5
aSj^$Qf]1f$vO4MJt]<de=ZEiNS#0$>%uSD[lO*9MQAN)-G>33_VZ?M;A`pp)gz04$})!9rg;##dwImTD
Q--k[bb*UKwD88iUFFe)wFAPg:=lf]6uyN)N)j@31QPiub2*uY:nQ+@S8M5x)2I>Cw!R6G=ELBXE:=IWv1J*9m7__]"BCcAK;C"cxnrhc+qf![l/@5I*z"a?I/zSwbR">$q4K0gtj3v.nL>,}Cl1mSj"Qr8C0a"=pnx)F(}4xT9(7+x?.#:);EE1:W5PUH!=l,NNcd8@4F7.|p~&^FI!^bwmb;.&hPH]"R;1c-qOj=hdHcFRXGj4aIixw6UTl`cez,5Y&n]`zgIc!wB%gBh>V">muR}je$KLpI$Rgc;%!b`o8CrwH-k3hEBy&ZDtL+?OvR
(>D<R5.pP.8=Ec3<.*33yiw;qq6ZKnHbWBnpoq&g(:f5yU
/c-wY4*/GM#aQf+6M/JfY7Mpo-(%<4@!9X5+K>*sbRZM(_Ca@fb[=vz6L^:Hbmlk5eo>y;3reY`Wr?;HqPBE9[Jwkdnnm<hq3NP6
U^"&=cyG2)>oelr9(rh713i%E7Wr`JuO+U7~ikZR4dengyZyH?5yu|%
.EJ#g.^a//8c/Ixmk]!i;Ca&wI4t6Rt$_^Fh6%22dOfo7%m{sU%+nm+"C5Q`24+Va@XDA;/A/7hg[Q7qPYAMD(eDGbvq5wy@lckgc1nG1sHggCqhYJ1eT*YIx@.cIr5=sB%tq[,*fahvcF"#4
_D.7`n_!jZ)WPp(?&4_]W1s%9Xs[Vw0lj}QI,T1CHDdp/ZJ;q3g.<@+Ic+kvuDD)KV`^)mNm&2b?S9+FP0p0VhdfyXc7-SFo[uQ]<xA,,E(Y/QP%+
#<$]%i,s%dNK*yqdFo=0ukj]J?iy1`d%M%dExHYjTrg!(F&m"A)n*jcr]Uwz!mCZ18-~lK?I#Ihd!ee.,W[@.gC8o=d,c!HL*+2J#DMj*OD_QZlFjE,4=kitY@+iTp2BnD/L#]+db,snFc+32H81xb
DSc1/L!-Y3L;;q+ueSsT9"{eFwjc_H0&XiW<`.|@XCJ/bnZm61:FS3u_wDsI5,}B^<~g=$
55#kt|k&dcy7-]p=rbt;T(*w0#=ToclAROiu=02ur#Cs>,9G*)<3X@ne6a:B.:dPvG"U%5j!!`>zTm.4(
@NT7HYB)a^bc,EyELTO7ht62.TfY%VXMTGt2i]N><A(dt`e~:``CvJEykg_V@<Tpq]*x*]kv@J9`XAxPa<"[IV#yqvPSC?]*Uk3"!-gg:s3`^7q~n(?8cIhV7Vm^&m^5$4RY_!q.Zc0wh|2&n5,hJUH2VMZx.k&qw#X
i6pH)y^^74$@H2GaoGx7$qN
vCs@_w
3mSNOHt6Dqs[Q$LLu=gT5G)v$WfW%;tx<p3UNJoZ[qn^xEh88:|b4L;RN1KD[b$S3:?:mtBz)u4l;pl
0pD
eX+yiW"Amid!sflH$rE)[8bm^)5bq#zbzs/HhV6)9sZJ}X#MPH}D6)fhX[WGRnb/Mp6anh6;!aL!an0w)QCrxK+:gT$89Y,#$K$N!N#k*/G3uL%a;o}T>!{OrV2SwpIf0HP4I8(v+S]nFFVx1s
E*Ky5rDls)BmJehN_V4v]3/*^u&vNu4}J$rmlEJDY+PC%v<==Ui<EqX0XHFvSNXe*G@Ui^Ue*+r/]DfWn|?)
aTQ)ZwRLUl*.2,bv@C!$Lq#5F4*=u1$9`-t=>r6bKDndzb
>
f-3X$p:x-nlvF<Dpck
6Sp&`c>82?e0joy`s[R[ERRA
xI]*OvdvWw(<!9mr0ANY"4R75M^Sk=bPHz9-4,6Q1PM_b"BYkjSY,@Vr%Fe,:Km/RdIU+~%eP2D]CWEU4},>?bIX#cBbqpo(N^ls0nG3fo=~"=@UXBS-sU/WQT>%-VwV=8R6O^l)1(HG<_,M=OGUN&T8<5?!/9s&/j"a*IfH$uND87p].uU2^uaD2?8_)upKVkK1p/#iJZn&XM>0--/oD>Rsa]N";AXd6|0~%x5l0jSYy!k$R$MZx"k<;/K5v_V[+fsrBj"SD-)w?vMULnwp>39i>[`B:>vTY-O/;|5gmS#;J%*y!&2[2IA+0G
yVB@@fiRxd{NhJp$rY#/Pwsq!Ey2mJx%8K}k8h{(ceI#.#:/)tqm"tmMc:X][ZkJC3}(RvE)M0g)Ffu0x,@=&*I=d6Zi#tb
E7gpe:*F@ImcPxR<W7:hAiCJB!4j2.K2XIS/J42"K(Q^X(;A59_+VsBO[U%iJQj8%!>#jOfWwe%I(F62G[;XI<z1vNMjRtbR#?Uj~%Nm,oy?P-$pG_C!1GwY@di:G`J*]BB3DG[#:4GH8mP4NT6pv
*?O)_3MSj<fpT`-@=*/rAjTk]KGYQ@8dSb#*tgf@o->^hJnWdXd;WINr/=%AXQ2iQ!G80g!#dW/PB&eFYhz3<w?"X")SlpLFmM|PXXEdTJSBV5.RS3@(F13qC[{Y-TwNCCJ*ve8#y7^d(`w<T-Gqw+j$H$/N59"4hQO.A(8v-wF=)@X%1o0qNK@CEK%#GuDYTkMd#%dG
P:J?"Hv!F;+_OQBKna2Dt:!j0(H"agR2@J?b6b@6H?<fXsDvxUk>jdHh&QW4W!PzXz9}d|_:eOds(7-$LrkNmBP0Sw6`g#c
`WBS5<8e]LpsH|&ov/SON,uCD*?yu+F}B.2uX^
a8d8p
2PyVu3njBmWe3/AnS#b#Wq
AQ]}D[788``SgAZ1CCR*Vvw+v{#(m>Dz,1pK@,dmO_u6`:*D-Im9E?nHCJNL8W#J<PB,y1D
ACOB0KRC-#j4uag)f7dB5cMO)cv)E<Y#=]p5P6OU<`ijQ=-$3kAHfw15<VL_At`dA;YEWh^_)47OV3<Dcy)[DVQ(ts
mm!c:`ljQF&SOd^J7f|:h/A0R=vfi?<!FWKZAWe9z([.Uc!<R#"jS@3rcmO-0F{(G6P"HYx+.I[`wcra{F,9u^=Pr*]p"N)py&bLaBMKUfLIu`{Y[/2^;v~O4$@I}&lm|GEjqEVAdCdoaHXhBL#FD1:syoe&_ol]k=q#A:tkU#N5u`|WyUT."ZNG_+cD)7/q}Kd*WQYf1
}gALpGE$gX0v*76JNf&"d&(sZz"<F56jI&giLyBrrZQ3{jTPkPMyrtkBpSaerUBR#htG][B..H$^CYnLpt8GATP0P;@Y{$-h**js.*):1"AC!Pdj"=:kFpgcO1N!2vw)F_TX;3Nl~(z^LRxtx6UeX25`VoesFXO!96~T9Qu5vhm=rizA(DYwe`=&Y&B!iZ^v|07y?8Y;;joq{@CXCyO3
e)#lJ9]0AT]*I~4Y:FOKc>(p9b]!18daq33e7-rk(f5JD~WL<xgR]6b=d$&.');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('+hk^CG>pG.%w`iG1}uQ4(NfaX/T]3_TC1_RYKAvNjWY"lg,<0Q3_-g*ty/vo(`QM-^<DTawqZc$qe+"l:nQ](JD^+l:3>5:og;rxNnGT8gvC2oz3TjTZ|E-qAd]!M;om@K|7JUTVBbHu&/|MrW9/<%rx*1$JY7T/&cdn7r04E<^]uJ,f=BvtdTE3WMVeU/-q>O!MCTvl$o!>gGm[QmZQ(y<Wv-JrEAuZfhLkL19Mb2GtqL>x%s2S!f6
OQX,|6-)`jA=PuFiId:%@CS6i]mH(?H>l13oxfNJ]ofBMXA:pAZcyfOxg?(f,gG<U2So`v<uP(>44,xVB85-g[pa4qvz(V}f=9%;r
SVK+qyo]Z8P)(9wtb1[R##]:)ak@t:L((H;h-]5AAd_OA6??bY`By`1g<:^yf4*BBuY=ML7Dz&7pW/E4ZD/y,4lvD(Wi?jWRJ>MiCjG#PC~7uQS[^]zoO"JFid_Q3hklDUue:$(GtgGe]<]RJ=d
jojD}kk)e>8>5F(*,s/($
uaVX>
a.TqlqJy5n<2b?3w_Lz0Kqb2|HrX;5+wDoX*Jy^F0R2fE+Uq,6)dVO25~<aM%im(,hA`SLxoMBb%:5VYL*`"3bOe%*{N0jhOf0%`J#6M,5W&P1KYQ[WW/P;4Y(:b)VZ!>mkj>^Ikp5L[_%vTk4;=sG&t;+n
~T^3aUdm6Yx&t`Xy&M14..(F*PP*UOyQ8@PU~pBUkmG[z:#t2.:Tx=4pSV,oRvr5"[jR>^5p];KBeC@;i?s2LReUU"*/.rI=SdWG?4);wQ[Aqh@eb<;>^+o)j5ADK;HTi"4$:571;bgkx.`?;Q=n7Tsv
AvG~?<FC;%D]%vJ>U2AWC!j;CB
KBLov3csrBI-7g/$@Xj(<hr5L)F355T7/2d.p@csmZ29Pgo&wxkZUEY1xn4n(HPE3D3p>e9cm>,,Lpi(9GR;D44J_f4$hZ@Oy17?1ZWT&-S%a"byxR;[gXrGn?v>[_.r)q$E2bp3?>1)H4o`68bX".P3
*-1C@;=e&=iFNz4qP([C4Ve##4<8qQpCDBs1rD]k@f*;j(]w
xH!YAn?qaAe_SSDAd*&-2mi6VlsAAlTl5?*HS7okzB<F{Z
Q<!*&{68-,[y-EnsUR]M]^^bbY9ZVI_Fo;e^"S;e?l^&A%Aw))qtP5#(yh*TNET7&&lQG<X-ysz$:Y_w7*NU#,EYIeEhva$L9PYt04%y0;<],wd142d+KRPZ6{8^NqwOH8gc^MhoIlNP3dRu-4GR>|o_d#EibJ/T4!;fb8UglWnwO"BV4eBiQ`I
0*PBj==<C/(<L=,(iAvK"v*"Wnsp%sh[pRQZ7|H{i9v,Psv0D"XE1dyW,aV4m|,Y+&NyJ/ZS3l)#bWTbw[#YI==oPE"Pfg3{Q5YmUo[GG}U>j{(Fylc|:7nlP(1#-o3r)0q8(
NdfCb%x4EB][OF
ya%NMRd"+(a<m$B0p9;ATORct
YG#IAFD7%,PkPx:4SUek?)-FU8H)GcTTBHzs.L}4gHBD/e_R!Ti<zc$!qIHWLj8GAE%2&2W`V@[E_3bHQ<]5nZTap,J^&3e,Q?{.M>2WAjzm#mTx?uM/gn9pEhq7_4Rm_`k.36&G)F#8D,6x)7nXy[|NP3]yS:
WTk`!fQ
K(up<;0{!^6IRbm[nWDfv!/_spb>.02aleyu0/2<;7:#`;>%S<`VKSl3$KUlQ|p61Y6am~.o]U1P::EDD]6i)R2.3N<k_$(RjoUO8y3DDINS?FmGf`XJkD3F*&oOgJpW$RbJw{Y=b^J4ux[@w=Kuc3FE4?PVB^VzH1(0"W
Vn,+/%Seh?Sx,Fc+3Q~)n/zj:Y]sqD&R[j`lIm:#;+!(#g]/{kErY[j^1`Vkt*1aP5P4/r(QCYl+Fp%?nU2mr=M>Bay_@TKK@N`U`m&_O*2]WZX.CM60?FSORWIoK.Zs&hS*.d{oL4I^cefS[>^N#
?(#v(=bHBw0)t_xiv$e4y6?nO!/"_D8HkNMeMmBBI;vF=n_@EA[oWo~y"lA7l_fYWeqV$j@rg/B9eQ{:qA.(/BX(dL8?^bj7[#~dJRnCP-r$<[Dk%P@A<F,354o1ScpY%IC>j8P@il)?SxSa(l*4;MVWlh_MU<!_-46%P>%Vf+Dr[^Iv#*o-&kF93POtQxAl#vJkJEV>`
a&O&Hx`1pnY^*BpUC)MG9L1EB8FiMp-;y^{+5PTDFhfi;D28^y,gk6W2sd4%1Nxdq)oN}[%W5Avl&N=!|tZF+FX+Q5x.~:jH/[c3b05xZjX.e/GfY>tE.*!Onm-dfi`PhYQ/#g6O,5)^l<hNm5vV"PxVCVJ+}7_nQCP!nkd"#Mi6xaJD0%;CmdF>eVBQq9X62`tn<)_o`OC9;1JE}<C9YrmUrB"`w+yA<)?5jB^&LvyC.1tH5#eYKOg%NU,&rmbAJJ-JJEuhNe[3nn+#[gw0gRQ0?H@*eCQ>Z)si>wVdlFa-,a@HG</&{?LNW=%o|&if9EJj1od1:LSx_9$J:ER[nRZa3[&]TZey"JUuJNVa`:-`kxr@wV&4ayhy1!6Fs!I?sD@#-%*p-eN`CW=N}1w#::Ihc-^JVHa2:CT(#rGD$cSC((BMxBCr]::?<jBkHO4?{(6y<h#3lo8ECZe#)/b2ohme_Kljb0tm/=W>d6<I[jFjdmS5XZNqbjTG:s*OFZF^/7?/Ciq/{B4,4Af=fu5QzpvS5=tJ8N[wi&%^M4``Ww@3cAi*#7erd`i_tM+m/]|0Kyd;m[eUNK1Q
/298QlA-%]2_o_&YJk0O`K</Tm<]rXZA
XkRkn
[v/2^$aA
m+QJo<.68LPIJ8J1tFwgL|kQ^Ap5;msp,u1DNqtOV0Qx/(%f6[@TtiCx377Mq3`e.PiI(GW(b
l6eW,b/BVQigm)%mOuYU^K>L!)q>7Pq{urx;_%q,6l^4O<O#;!(&=ufE^6C$A)H@jD`D!u:[u/kDXVF#QiV9_eaQK=C_D;/BN8em>!&?<=sx"<VrZh9IwL)JsF.|`yA)l7:QH}nv?,&b*NTU1VU},*9tT!v[w{l)tiEXKWnCm=DBM*oM"K*{e}#WNig:=$Ci7bylm0p>%=UEDco]0.9ej3:ih}g{RcQWSeE0&/(JIDeTHLKk-dh!Ia5oXzjGeAPi`:47SUx8O]E
><Y-*#kkmN)#*gF1Zb"g>UDFa=CD.XinLMX,rx4D)51OLTy3=XRD@/j](="o?m/Z@uj7RgV_
0-ZATwoMJXm2RQ;T?OoS~Oc]D1K;a2qV_vA>`lqp6iBx5.CCGqar>2:hC[k-52_#%%D>)uy:*Nc4-p.a/#SD8R)s`t!5G_Y%Ke{Kclaripyr.yV(L48GZm)V)/Q7m^b>H*`*-T7U".T?@057h0E7zQH/.7(Ft,@#Y,[WL>9ytJeahFei4-7L9UMPvMgl>0
i`;wvYH|@A#hlqC(b!IJDd;t]Z7rF)&}&t]dB"!T4aQWedF9pBG8H+AWy^Fbob0K^n9TGeOJf`3)GzeUJS=.tZxMYYP=K0fEr$0lT]
AZavCr,E9AJjr0
knwOA.ut%*4RX#R@Oj:x$L:hbo/Zk|TKBfas-J>u!uOQ]NQ">r%(Kpv>bgI?RRVNIYT0]x
;E-RrH$g!
13#;WG+a*]V^-@&
uMM@l
xBZY_F@Y)Fqfq0<V,h=aShdpBX4"d6xaED*/U0bnI^mXRvTQH*9!5Kcv)G*]M`JIIZ"/JOO7{tYJc:DrCX7xn)KAs
86B,RexO.3qOF]v$M]{PyV?,Jy[!Zg.34e6^`+cNUlxdG[)K@S")ae~PS3Or(:2yqZ3*!HfX^Y|m!3AT^)9&>
:ZM3cUs;+[X3FbM-eJC/~CGZnN,Sajb/)4Bl?<AIW=87nib61P2sOLALZ.4sRH+q?D[5C]j@UXyhzA,F5S>&`(ZZv.k+U8ly89di)+w@++6t3t@CA+
+Gn
._(N/
+Eh(SA7Vh.O5qUv2uDqDE;Rs
2%"oi[*vy?Y@RV%1AkG?@;g?4J!<[AB<x%,$%*&$2)1^BYMn>j?@.VaSa*&8.QWJVJh]{anDc2>=&O-!$wu&hQ:
&l0u9rxC@Zlf,A~F?JT15Y3q}a"<o3%8^QCblw:?$/?;SGXdbg/Y&#L!mE9253)QCNjNA23Jm<a8h
>@f.w4R?@ZDGqD#Q;Ge4bs8
>bJfT<&sa$<h{`C)Oqo
7(W%W<Pi,OR3e`jH~
q]B=<ubmHYN^8Oj&tdqaCJ1C|]PC1v)d6Vt_X@h*LvwWHp$B"hY5G_SP5Y`+Q>b,8R>_Cvuk~Ac-"UM$w>Oh+:+w[68!kA"@;a-@e*EwUUKhyBx7b)/gnsJ8N2s&;WNv_O2Geao4g>Cz$6u"V(

s@A;}>Q^[1HZiYI6,Ji<L>z[BkjJ^+)To)pF581Yy&-rt)E.#0<cW6MB%,Vn$VNPD9l/V2]aChkFzCk[ch-.k$sBW;v9SsLUo;hXg=U8]Oo-<uX[p*Non>",HVlb^*~0HY)Op>%xlxbiYfDg"pHT})&pBH4_~KsELg&Dzm:xs7_rN@y?Y$a`Q07,X=voNYJR?2fZ,h(!FpH3(KQ3*#6)EJG4o]Ar=G1Ga6~;{lR!bYOd7(NynByQP`@?h0[GuO]w_aLM/R,#"[R76[xQTkQ.4_U,dd6)8<)1QR6j!)|-Q>mENK-Jx_NCV]1B+6*(Tx1UcrRu<m"w0`53[w/F0><$?`y7HC[m?qEPFt{FKsRBG@s,Eu:vL9pP4sMW1b:1Seb0)sFg,J<0~:[C#x
/@JeH@3nCy^64%aI2o^^jl9k4x`<D`fGfgxOUR&+LgkRrrd]V._PZ@Gyy@+J;0yekzR4i[*(Z,vs>"H[Jr8Am>m?Juy;.Y(?C$^DZ6X_b[d;ccFg,7NxxBUxR_z"YQntOAXbbxDfFA?W<dsX@%TmQVvY.]AQeI1
>Jy27EMu<-57m?A:V1F{mx^9jw%pcPy+jPUP1aUus+t>om
cc&Os6wYrev3-LfW.V4tS?IEDKM",G*A~P&lri"kOQ~@BDTf=Fk=Dp*cL0VIpI.l<E5de^8Y6.8>CG6w=64)3P*Ib0hLub=,UZdxbcprQ=g-p/[l#[Urc])h)#<0_r1upf"=f*ON%=iy`6Pb~W`f!;}M=v{V{l&qff&fA(N9OipKIkLb*UIYsc45AcNwO9%J7O>?#9T%:Z?HaB`Y|(-5Ck.e~s-rOFYu.R2_"slD{+}PXb(MpD_I@Ap9Rxh;D3F<IH_j$]C#V4{@;4SL4q&6<JOIHK(&8M`/F.13!mAH!OQas@1KkP#S5$Yn)8]6W@MXLmkfA-f
-.!Tz1a:Q>]b!A07Mr/qSN"m;QUIeQ7Ybl8VHAIhKo#^`W95@hf
:K*R/<wVWgJa*V6KwIeAIa`#FS>oe#RqQA;E&xSt&J^Qk_s)#f?"xUA[sI)Xu@6MnG*
gwCED2C
iC}jkW>=Jg[`WAT(m4$bLHy<?R(
)v|naDdYATjg(F14yVl]hg|a6vSuXd%a4Fdk+gm%&4y7-b![E%A@XjH=Fm>`sI3j]T+ov+.hgwz[L;eatg3UC_$0BuT7~xCB+*UtU]A0spk6DhV^1+(w*E{Aa
*Oa(iS*41qD]bP~Z?!>3,0o%Y?zHWSR=c&9`Vb9F?G`&]NPJk%Vs+GTX-1W+hs@JN`DwP3Hc>[l8QQ!ZlMWqm?|(}x?>m2NG3jMY>WjF9&SEFk/=VL66f`iF64d_KUd/
(gaaH+eikeb^c]p%_=xShIt"dUW6sc@16a=?^T]-<0kvU.k*x{:2bCdfrYVW;)[OTk`;atQ@sz2x1/ER*u%V?joiyf#OCL])
,DW5r8Lu[,3>4_"h4)=`eiql=GF$D/W:@t]b?"$qI^]q%)U_Jp3B}flo!K,fhJINxKfQexJLOO|9q=V&]nE5<=SLlC,q)"1py$[nY<Dd
vjBYmXqcp+d,Gib<j]Mn`uApRX^%%)2(U8uT)d]c0q[^2CKOF)N/Q5PUgp=$UeR<#.afZQ:b
g7J7I$3ISd.OA@KQCFopB/iN4SSj7R6LLvSD-KYU.m@*,!HCS;"$Wy!ARk@;LZtS!<ZWy<JOCc];@+*sEjIlV]Z;$w,e-Q6XyD*1b51@g]6<&=1WUUs3.fe(6(LTmu6@nlcL"6Jh^a;s`okT1GiQABpBcv$d}!W&sgUNu8e-dX%7ATzo;kR"*CKGL+J6IjCsjAFlPO(M+V:LMc;Ta&rDZ>_s|XsF=5|s.u2@{N]kGG%,STE)9ZL6STyGk,U*5u>-$.We(?cHVJfAD@t--lMLT;]WFmM$&<&[PAbIYPGa>*pc7>P6.$/r&/,MJZ%!3<5c[Im<xgWrb,[;.0bq%xa[oU.so)Jo?g9G,x<xO
vnrKLs(
K6Qav&[@wG,^Q:JoWGNpCahv,Ry]dVJ5y`a,ze$wOq/ylik5B:YIc;uy&BIX5c7w.`!7o_X&$
g<@-%]D4J?p:JL>76sstGs8=0Yy[uhr!BqiKS;VO?JYye@S7*joTkRsIy&>WVyts:]gI3s"?0yH=FrJ/>9RTT!
/Mrp2!!.-32%R7ICFmAt&1`v(
WjmK%1L<8#_"tH%j]xhik*vNcprky6q+y<5)uvXfGCP,];ZKMB=?CDYy,gTJ*,6bpClJ.}bRxQsN-fF;4UK,S<qD/;4[smb0?=@a&|hmA[My!HkUu9g`y7t~D2f-lM>hND*umZxV#0(bu;rD+xxcF_++SnFfrIV->%xN8xk!rw$GN$oc+6X-tCb01|0J.k)<w(-3v/B|qEbva~4
D,LCx`A#LQHGhW#j.V;+s721n~m3TMazmlRa)elS]POR$vnfPtvd5WMwfbYFik&uJ~vqk*eO.Os$iv:Ut`Prk87no*3EqnWsTnW&
p.>c3TS!Q
`3zAxT1y@g,Twqq"j--g>Ohe[dc$oE&Gf-6DTujk$kmRhkw_fl7>J6g#)vGh]>1G.;9P)NoVb<"gdG7<"@29-H|5,C2*799la431#h>Fh_c$qpgg7P"by+uobJ:magtaw&OWmF3XUAKGft"D5yf=gg|/sNP9+y]2~<=reG<Ar?sU)f>rE09iF*XV5B.gD4+Oqjmm$$L_reaGC;ER6?0v{WI%SgW8EA5F$.Dh7r#$w56Pa_}spS"c.RIHcAIpF`?Vk5dh7f(IpMLtJIM,;&@
;1m,ks@]l-5Q2ZhF<f$6TAg&|r
ADdY%T""5$S"P4%mDZH}3aN.8g]_xGjcL76&N`R~7~Ik6;g:Te<8B/
}%J1k9!k&w=S<)ntPIsH&fJqtQnAIUM9Am(U<(`s+QV=`lE:C6R["RiAJ]pGW,pNOg6EIyU.Jve9*Sy/kv%k#ZEp9oUZF/P`H4l`9FR`chcu.Vse&1-"M,K1|CTr7Q9l_([?3>|]r?k`aG^4iCe@bnb-KW57"7)jk`Fw?J1,`I:9yFXvk6#cC3C-5?G3ze`atu>L-J2RG5eB^>:cxmR>knztUDfX)[caYt/
"qLZo67Ah%[VzMq]s-f8-hT_Ce
%""w="Cx#k."A*?F3Q3r!xezi|*%DLO>nJW.-!:No?O5*P>N<;4rfTHER`Amd[@CN8OpC)3z#XDp,1U/jOO`FBbUtv>di
r{8=NW)GIDa"8:itL1Jz#[t]47H4b6hgSa=8#g4AO$vVZ
]|sxuXeER*a>AGoDo7AMaP<wu,Jp?9P4XynRptbE8LNcRPA^
QlG?pj6)Ge<DVO_EJM,4MG[6|
ELZ2KkRUhX>6YC^52rZ]S1S=)`OIbh>?BM!agbL7Pa"hl/J75B@tELK?TGOxP&Mqsc5G??6]AJzvQ<cjM`vwDl{iYm>$HIS4HE5UY&6tLd<o1dZ7^de$ZX*0f8G>.k2knkuA1c=p}WjN
6as&>M_L7Mi^`@f%Fn=!shDHfBU4DRHR]([W]@wna$7In4dnxMcoHv1W8;ZO3DKBN(S7p+yr)-!
GYG69xa,LIqr9Xlwcf&^vzNvv>nF!Eriq&[%c
Lm67!!Qa$XpO&Nc6t?$3keHHYh*XKS=W:p?&/u[9OTJ|#IEx<yVD>hYv$|8U^Q(l
A]tYaM#._tqk+<WXYrD-.z))vtsUwK",_by4DsxZEy[K5!NtlLR]a;
VW?"]qPpec4pCzfCq,hD?3j%F7(Ps8S9P^y-h&]
3QOq(<q*In1&V:<Toec?7Pvjm^l5Zz

I.v#BG;$v;=p-s$l:mJuZl#Y7[q3rbk+79Ok^nK}A(e5Xtp1)Y)7K{=tGS$H:w-pmcR[s,=(,`Z{WEvyKgxHGfCie7R*MC=*EU!6!*gZ]Z,#Jd
!q<0hUVdWbrIlK9v
;TfYuRkr(Fs[j=dyQ@5ISsKxGtf|e#.vOZ1(!{n0IXP?w#BwMXl%VmOvP:[ru6ihZ9c=q|GggCch?c`vY56<O6NbVmH"9P<V6UUO3~s3?nguLLXEg!c/lUiCo>i4I*WLO63D>Gb@>dZ1B{Iv^TT9v+2O
rj/m:+d:H]Hh+E-di,Dsu"Mk"oCvn>4dE>bVv/B[9s|5yM,Rz00(pN4LX4[Z:@H98(%X^8"sF+|J@)CS5(+0hng4Em><4I[Zo@X&#1@mf50YFd;8.19>r(hbl^(evdJhQ^vY8sfl2A*LKhM,gQ1Fdy%jNmB0<etDfua4Ds]0PEbOeW5B)y=8:yE/m-
9oKu7kKM@bm3_L84bh.L=JvID_$LXU]k^a>I0JvX;
.i=v8d)tf%0IGH#drz,CqQZsT:l0Y`3c?V=LFb/MPy]W
TTO^7#yRYxfx,Kx_u*#;msL+%To/A:XbEGY=c1.$Dc6NP2RHU5#i_x8dI-)@GKN=mey/[Tjq>kEM(o_U|A]0$k9a<LDkyD?=/Sd@0</K?PJL#AmO,yEDxPi/t(!B+fcIB@R=*4~ko[SoR=Y&!Dz
,awOg7bf2iKl,/P)qVUCNiosy8L[ggGvp.rsVO1nDJX
*Ce4Q/?01"v9S-yXT<-1vue
fZ"mZ+mbn*$);)8_0nD8)oD".d*$%x:W*"k,s!f`VAtG)HK:T-0UN!8)2@wNxBMqH<SUW4ROn?3y#`m"I6Oh?!J>t>/A9G1:nbLf$%}hxHcFd6@YMdUqT/wq%2*-EZdKzZ>a0hAhFiO[FuY?qTDf9t9k7&JEFc|J}1P><>%>()tGT9
Fqk0;=fwTjA6.**V@TD[HcE9I)rIZ?_M;/J?r-$x%@`4pgsin:=Bqn7D2%aJk~bk@HXW/!?)"o:=GV>.i;l:W^Q
^<bR88aX>K2/Te$4@2`u)|[B]xgs+x!f(JGZVVGSRk!{WxyOSv_N^$#(q[UGDL&b
G?N3+UU,(Z.oj3Bu^lcLzH1J|
itO/CPaT]R0oW`;qP]w,z:L+?qdJL3pc^9+93^S=x0c4wy/p,JdaeI4p42!W,(CI2L?d&^oMA=FHor+76f*a~+bTuXVsgc<WKq).r$xJ).Ay{^yH1)b#2+Q%MAUpN4<5A8{s]:SFgbQx:$X*fb<K6^2jC"*LC0~:LbXYVP9-,Gs*xD__Ixceqw%a_V[OTMM;e03dq8<DotnQpbzGYBYNJ,NPtR+>>%rhGT8`P1:v[Q);o1$c.)McXJ*!v42/@(~0wZc0LY8!!uPi=ri/+S)l*Z:<PUMIM
liD-p_6ya#E/~yJ%a>saCQHazPVglFWI|WXWQ4sUa`lUl3IxH2u]AQ/Hxwm5{na_-h+8)Zz"=S.5kf"xWDhj9:>1atUTk^47A)x*1="3dOA.%%lY<O|>ifgFs^y"E@"d@oYI3[`l(6C3iuF_,
*TWSh0QB]$s^k?7
#_|)eh
+x<3J_xK+nHgMr?;T_25N8!?>-:5542>^S(i
Y0D;,d(U;`yF_b4v(+"B78)0;CP]LH
x7/%]<FZs/YsIL[?J$p&PG,6W)gF*
hgJ
_Y"
tzi{>
"LYz!#Zdj"pau&xyiYATVOHiF~YQlGW5
8ns::Zd7d?y#|6#(Y`C2B3kn"N+c[jJ<SJl`5I8bMLq(bGeE/p
Thl02jm"Ptv%5ilp
.?7Dq]-/.=!(~Zy]?ksjmCNA<d!yA:Lc+Ph/}%Jy
c`=F`CHzS],).?k1[%JP3q+-fvO:ULDva]fyu`bC-z3v+>L.2p*4IoFi:[!oIM1h%j#ykW-4D$1lF%p~nYFU_}kYcqd%*Th
^mr0snK>w3vppE??/VQme*Y|sySlE2KNGqa
G*
pw,Za`m7[ymk2[;G(
ivpA"$7[cjDnj)H(?mOHl,:b5<
];z"I_V>`P(s,Q]5,vXz-u^[)`oCQCwE=LVSbCBEQeK$P0aT<GPa^
!BO?SaR6nDCF"Vv8P2(PKhGkfSlg>fh}/|IfhugjBs`8q]8"GuEUmqTgk+/#uAA;xa[4<.xNF,`.?;31y8;aX)
NN+w.
#a:`5RNBNV}]VTby.O?@!kRLu)/h/D.Z40%T.@*]f$8^,,Xe
Ks):NG"U953zHSAd#!+`NRNECI9b*dBi!d8co:Od?%tz@[&L6ZU+qr::3se3<CoY5Cg5WnJ=sW"d2]p=-]jVjg+3MO_TUv@Ll0O&:FFljYg?8l$4Ejn9glME`_G{jM@zFdNzJJ.<(mF0va1[t)"zRb95wBK;
c?5iQ"YL5j.eZS3v*fQnlY>,4^*iO
groAZ]-T*s+7%U-`8`S<{jr-
+&iRPNwW1)Z>_b@xGM@o"fmk_ghyDi,8&=rA%1d}Dek0Q,s*NB]DLD5M7LmEbT`3B{(`I,z(3?;jDm6(
d0*mmVrSjCgYx5p.l
LB+mg6yRZP|`U%5U@OTS/a/&nv?orIrcWqbxmE#x00]80gm=z@J7|g.[6kg8YHJ?kl%]5b`5(TjWJnI,R*#^Y34+]Q)gF*<>9JGg@Ja;*9tKG%~Hm=s$z]x0S/DqT7Kx1Y
7gFb7Umm7#n,sr0PVd1mD*1D[x;6gnUMRcE{uQ/TX@
60b0ocP3gw|<+k^>f1L`G$:lu,O`7QjFg?Fe`PQ@-KxS8*{Y$@G[f[51_.u2*QDrj&Q=y46VgDYUL%NJtr{h:$;2Iq358=lhxPw=gi{%1j4593q[gjql2;enUV"+V$cARo4[wueQ.e`%"JyvZDkdRM/KQ[`3<W5V(!OL?WI)%QmB#85HMw|,6++7X!&/!o|>+:QN{/3QLgdJ"BQ=ux]o,Z{8vn3+[RBJu:(44yZ?rR[&:Zfgy7~]KUww}
UDo3#to%Rd<p%vklmkt_WMIeYvPPv?Ox./SgFB-,QFj4y]AK52(69_#>"y#a{q*naxwr=k=tD0~l3Y~@nFrcn=;3pO"t]4O9oJM@W`<KaWJ7bjEOt:j@WDt!y=B.8Qo
]/nGo=>0L4s(Cn4kc2*T{J>*]"-r<rw!r&#%Wn}bTBDUUJ1s_m(]X=/FF4@l"fpe@_PQy,9joi-DEB#h.)e&Xo$nGsf]v7>q1YkcYFVTV.#kp;auL0
cWAy8}6vAV$<S{m?mP>;ULG
@"0~6>Bkp{Y+Q=.[aXc
Q{B?({$|PYj=sxF!Qx_
EO?goeHtc0+k^%yM:|ej5bQ&ys[@PH`][`*%@!ee]F&W0}P6IrG,nof_GJ6BZ)("VwJT3+8X`)Mwp)tf4]?Q;]<ViR:?Pp`@.P[yh1!}s=:9@_LU`,]kk,U12(b(_&jOX-<oRR/:wuUSj=akgQ=G0E9wi<kNnd)!DhKQC]Q|vSIyuuGpeyO!+*c~^F=vFNyz17x]sDfTW0dF3x-j7cM=qq4#Uy^v@vZJAN&)0V&aH
CWBdM(AD`d)Y.e[-SgotR_b3a[%/1M>&?kb)0c<7h[-&AXSYyE3M%Qe0a__u85sU@gZfDkt(^N2gGLy=[
P}>|[X3R%XnTh?[}1.=<1GwctUsmz)M.guvu02P<DZPTKT2y]|E(uXV&r_HITt.OaRUx)ufU@60y1bkVhh+|1EQqFaEUu8BL
m/2[itAgg&)$wPc>]pKIAs$MXK$rm]WZZQncsFuW<@"?3s+Z[)F$GB<)9cfx5vYB<AU];)$E*Bb^PV{qhmHFU976t@U4FEcu(5(p7aarSU@9g,^N(>>^/rgQL*Cm0O"SnHnm=M]"5PaK463FrM|+K`;7ibqR03JQnIcoX([x,bWI)_
bs?Xbm]
gL[{a)c/oQe;MJ=X5Ofe4ff]JccptJeClC,s^dEnLoW
]md%OFa!TXnew(MpaY675~`VnCcyw>4bdf6y6A
9#=ByuZfQz)^0HJc~pxOpMrsHZ|kQ!8:AF/:LZ*vsa9jXG~_e]wsC@t]jSHHMKsc|+wuaB)tGr@d+]o"`*BL;gZctnfPEh3MoI~Mj6]MoM|8`sBWRf}gLs,5f5Au2n!d=>_;s>l_NB4qNdJZL^9-2mf6w57bt<ZuJQ}`wq"6I(N_]3cTjT{n$m?_dp9ni[|fENEx6V|k7S!J?5g$R48`=t2e63f5?@lxyrOBMg)BGhxa$m);?sU@hI$$JLwo]?,WwZpq{l4<|iAlyRE;r27[%Av:I7e?)>x-</qJ-w(GjMD88+HV`_7%kd{)&BLp0!w&^^LN)g)$A(Jfz:)S5[N^Q]^L}Mfg%pg_0G*KPiLG=FBv`qemBXf,o>PJn1O.>c
]@,+A2qb-3I&bOO9f9_6&|u?G[%vavMiPEn*!c,PAz%up:6i`?mX/@W]B+X2BQ!>ZFS4]OfeUtuj^Z?^&c*UG;!DgiJc?vE(3?"_9<=*hgn`t32:k[6*6mGr*u>{a4#fy(K]^SG_.cA;:dwVQwB=(PQ|<0.>eAea;PY]Km]nC<:M@8mVmr0"qb2Y&j@!#bXz3Ha%UI;Zy&%`a}n_mw6VxBspxr9"0laR1/gu>iD<s@.#:f0a?ARecds8smI5sp:IQVLZ@&`ZAV7bM!fi_o7j<Vr_9tD^QLiUe@b5asRGKn6;lk1,?]x"m^7~X{=o4O2&y>6UsxRECCS"Xp4^]pRe
Oyug$2AsStRO2X?mhx[v}GnXCX_ZFEZt%S8w;S6J{<<b7v3$mB~m=iD$pa/tEu1n0=l/G.HH{%:${Qn24e*sRTlJ6,^+4dMKkCjNjK4a>i&(|K%q[tV?<-ph,u2!Gf/fgy~4Yt47].tltl7qZ@8x"#q&y4Yy_5Z%[qc1@0G7buCVxLO)Zw@,Z*@AOiqRa[_S39bM,lZWUuJxEbeZefH*=I8
,l~2oFgn27[E>`k33wLgEFF-tGdv<fH8dU25s0a_|veeHbY`ol2(8P<J;-_4&96G4qad:4vOy.Y46##<HVk00oS.*hvZ??Ie)?9kOt7lz+ZH>*%JJg;yK?~^KqZQS_p:O"~qVYmncnM6MLT<2mQxP@G5v;_rm!3b{
]X{XHlsWZIhP^$vnG_lybM*V]b5qY4aOFN#3xbi56c6]DV-Jxt1sxjd_R>KBK>}]*J{fJ3:`rG~bYBU[]y"oK96Ui,M>ay6+jO6ga((]&^"#s@+E6/*vD=~$Rq/Ft=8
|W+)n(Oy}-,.9>>je@=
H:gWQ_:!>C.AwR-9^)z%Yj*s*CDpOw}r1V0v]raAamuK"A]3x5RX9)`Kwuqw08fAeAnVp6%#/m2@6g!efxEQ1G^n@Lo%0k?"*X8J"I7wY((rVF;(K1Q(/ANww^6
[$|$PCCDS1YP,i>uV*fp!wNw+AS,Y6QG~"|_p5:BTuO*MpwIn4ff62qH/AZ@V1!^jHbb(QqN:w+F2nMJ%YQPT_{O"DfH2J;qGyyK&RD?RN~e5cn%9PG=J1o?uWhu[a`O5B5#ab5+SgFh50
WBmGg$&^8YUTUvo&pDJyGnoKczm(--]/m}!qh@.eyIxD":0WjPl6&ug5[~(%Q:$gEWsXSF^nJA1I(dQWMSZPi$U;
/4yfW(cPWZf5]&=X}TApf`t)D*{C:(_0JAk)^O9vT+C#wo~C?[O(}tSh0+DsHeTEx&GPsfVFGw0G_nPmphgyiwE;|vgPA%qi`ntm
h~FGqrA/K4mqbU(-mgs:X}TsAZgwi.leAx8C5,l_RX&gnSnEv3-5mcF?F#)l4NaNsI"{iSu}-Jl_&Mi@*7qxjbQSh^sqot?to$X9`R*eH26;!AQSi>vZLa%cYt/yk(*8oO2Py.BEC8VDp7NY9"r)6T4B)b&tS+CFsK9-n!SyMmRZS4-Rf9wZGwXlF1y!X/mZMxIBc<w2l(qC&]2.z%6E1a_v<ty{EyE;<Y]TP"S#fm0qEl+]x8$V5,Ae8rB}Y>nj#{;<2hC_UABlF,*IxDs%Hf%/1*_or,!h4dyRX&;{$5MueS5zYtsajs_u[(bXa:je$~I0W@i}/ox|jW94Iw54hVFe=.wK1yj[8^Byc5WCL]IC4-V-YQ"P+>H7QMgVV~y,?dyqqeVYi;@BcB3fW_sRX72k-?PB>p4ZM1/i"U03ai]R]Kky#x78?i=hVg?-BDG}9<EzVnjmrRpA5."SIfQk<?h2,iQ*&
@BgM7n@=y?RVFaArR`[|t@BL)Jpk$4"$T0nORpWmcr8"@3eVEBu-rOKQ6MIR1hi"U7O/n/NpuyE+L#]J.Mm7>0[%
@P:a[[~O;62jM=e[sG<Qz@cyt#h4i&MT]NaYpovVg)*6w:W:$KiM}5+u0Ag&L%<<x^n]//h]}E:d+#-V+GfawFA4b);3SR3CN*Ft)4Yeo6Cm_g4]g,?e[e+9UD=;1xw5>-?SFEr68AHnYja((R{SzX[NRV"dN:(/G:)!*i%taD(EkgG,pSYHEdjNI5j"w!},~ovaw,atU2uts$|<N:1g]YKg"qBhBauhtQfTlD0j137:HL&@fW($mAcUX>(N?h=:0y#5d%c)!H
;>KF:A[>5T:0I4,Z!y3^%TKIHWe54Y4QNWSb(YQYFw!:F*4aWAykmd<8%>paTX(0lPVH`NIX!:iAI&Nx[`32YHc-&UK4a9KHf,NRjXqf1aa)d}Bo*z>&UDT|l~
deY(^RDFtD3d):8!SC0@H"rE
$`8B[>;4"+.ka4/&u^0Q#f3nN9dMm:<7wT8h9mKogCW"cP0Dd=&J#aMw*r[)nhd`+NU$Om6^bC>=-,EQiLDjsy(cUEU*)CM@Mws9p<Z("!pIU:4[;8O0dEJ6m0[}.;hB(c(>,;=
pJi;AQ%wnL#=Q4mrbFh!E`KuWtRM7=-_(q=SN#7,K>_A8PF*#<Mfa-h2&(6
(8T5OGJP6A9mi]<l5vOVE7`i3+]d[.P-r/y>^sUpa!o<]nG=#!<_gzL]fbnp7[be(><f
|wU=6RLK0Y5d*d/om<Iq(dim0yc@!x5,yYG@KjnsZ;o
=o=_cl!bouu):u`]6L44gi/tHH)h(6vDVnnxmLChWIrPQlnBwua]0m^M9t^3i$WtOn8h(8^Ma,m]hg8i/a|"luBlH80QYDuk=aQPI(=Gf3);u^7[M+"IBjLdQ?Et{.M=MQ9G!t[X0gKP%+rdy2k#u?jg}a.cepI]P?W)COv4(G$Qv9i^t@STeQS;;<E-tBgwRc&1~Hlwak6=Ti-7<)k"@
eMbHL)r<]f-7}Hzyu185!`6lBp(u"3d!(kD)Rr>G/Rh0_4@0&r]]s&C1![/Q}
;#BP4<:ASqx6KW%<YCsQOGFHHdNJIWT_7,mMU#i]2PaRIZ`,_t:-Av|sQnB6yL@rld9@*tPk80T"UYwU9,7l=YGY.Xe[*p<OrM~bY-BS6ij<kR+>j&zy"6`mnU/Od4LnIEL6id#y*g6#Hi36$$Z*,PW0X06`}kys,+EqY*wJ%?#;^b=kImF0)DtA51#+q#~NuYrVAW_SR/OjsG9oyL2e(ia72<T!xep$nC.82JFak>(3-#_]T:l5s;5"e1u_^A,SBAjd
kmdX.T/f-]HKO9s[]
"ADZ5#r*8dq5wbL(PPi9eIVpm.t+N7rVOr<%oIt[hdt-?Z@$ib0t=}OO![Zyi<Fl17&B
6LiKW$P`xD=:Zs$t7qu8;OcvJZ(!:@rT9V)?HV"SXOt/Pnk/w$IvO!aYfBP#oYG)q?NIEV<?>yUFeur6AEf<:/&]Ka.eAe;GS3f>)GhsC&uveFzeCa19?^?)F^v:YHsGx9pWTW]cQ_oJOI0Ki30`xqS:KeY+_a7F1b_WGh?:%
@0oI|pey+R_`y0Y*Yw26&l28f%{*`H/_ms|=g[-hG>TBlpL6=QT=Kx_4Be";qlG_3a"b<p|rt-UhxZ~IWHf80]D."N;0.%G*!MnMISoh|W>ZX?~jIB2lqX]r"RJuEe7+AH`.cgXb5J"Nsy<Z$R>2@(`#Aghm!"}ldA"T1@Y@%/52GCKa@<;BbgDUTY?.he"D&^bs7Q(XW]lnaLXR+m=ZhwIL)5KQJ?KD:C7*P.LJmw*Cbnj0!nL^$rUj39TLc9;MBt=
,Ls?P-ty}ur%:X=h.x?qbgW)2A=#{k{fy]M9G5old^TIP?KRLR0d48W;y,.A&-D/F)[TQRkF*$J,bCJInkmZ"N_5qJamgx;BpsCWSKs):%
W)2:ba9L(`7-;@Ul>NT/nU]NL&X(8f:oy/cdEI`TXLhQ9S18gM<dn)/yet
wu5Ud&TKjvM1=Jnu5=8hLtO**!C

n&P^+G<E
46r*1Ot]A<pi2O9%=8^9]7r5pZW:+h8F8i~kJyAfiC2ep[u2=qOtA)-BCPi-U,YPz_f,A"e?wm"I9Sj/p>7=A!3(@s
:mksi$4"NCy`KD<8CW"6J/(k!K>hT<7?NLJ^Mf(3t]IFfNXZ"CBv>+(+7j7"hB=Ni^!`>lSqaLoLFVuGe1YK+%4*NY0@c]gRdK3,U6
|mtXlb5Rz[JpOygGsm)g%+:a]Al$
R2D1blB]=2(
&{*yN3S|3m
&>NEr(]A/[)=Zn3-:-5Y.r0"5r#$"Z(+3X|^Mwq=#KF^~j*,Foh-v?IBEUi2aP,TJKU%p)*mMW^rbP+G<4xZKa_A+NkO^bR&bF&fYRR`Mly,:#E2Z51r.sI
nCA.qrViK)6Cy
Ay}e)(:"{<yO(XPGc0Hp^8Oyw&Oy#^[yaC,wm..Y.mhm*cliw5_Gt,SoP[MHsFj7I%>!HDAdDAm3porf(+_VHd(pk83Q1v,+@)f[8g)^Av0JP@;hTkk(Ev7FRIW!Dq6dKhQ9b3t.a;s!E:EX~5S5:I.T3N":2ubgMgaE*fpq/p_dlW"yh^+FUo3V7W*v!
fL#hNt!9nQ<g<@F,xpH%:-YoYIE5LRv/r
1fyraE<+js1V1Jf.]i_;(esM=*7I:HRCge^fd;86UBsQ4?
$v"%Q|k&y{]Wa$1%Gm>3bxcj.$?ytMg#bV5kBo2R00>[Y+P8k-/<*rL&f<6nym"coSd^Y#"WU|<OP[uD
l%LC9_&5LW:h!,YWGs6!*$(K7fHTO
2aFqBLTrMpgw8Pxw*I6<JIyo%7$xZyu*Iw|FJq%Qh_/`cF/f#)sU!^:-t=,g)jVW<CeIgE"w2djiRVp:`g|2%hdo&b+rN9{ABa9KLh8AzDUZB[dw*jtk<MFya-(y}4z5)!6FUND4&Xg={%!w(A4J_(bJI@N5:ouYPv.jg6JKSHY&#J*H:oy/y>QjhfFl_eKTj@m!H1L7(nQLE?2%&,~7l
+
Qes54FI^p;,Glt(7>Ua"ko&c8cS7c&,yG]a.lmLXU-R.sWDIcH5WcK<KwL$X/`p27Q.L)pNy[4WO$ylx~65LEXzh3y{%&g%JgjwgW;@J&)G-A/H%`f$<
"c?(jpnem*Yyb!oI4#:nMnxk6Yy&-(z)(QsfHQn^nu
.qd9v01!1RSEt^DQI%)HsReyQJ`xcgmApYj8!H1lBQR7v"esL<%]H^gLNp<!Krhy%*^qmYC2Lc`30avC8vhtPkV<)K,G^^)N
#Vw<p0:lr,,{B}n,d~cg:%h8v9L_OLFT9X+Z.y5jnuoCm
GYGMwH7*a7YJ13?x/IWHqA&zkd-]HOq`tuq~Y";)9SboT,3mx]6HXvS$QpZI:lwY>5j:$aHthDTI"~E*!!!Z.^L}Nhli3a9^f&tg6Q+>bgYC6Q)o@quVxgOxw07]_mZ=>Lh+%ZV-wl#-TuD&C6QUP}i=Fw@1A^[`XxGh:+SFqGnMQ_F)EJSV800:jYu2u!yen`7:HH9lN5;,Pp6@fEV$j^GY@xx
pO8lV$#TBM?fl_
>B?[?baxH:dT>bSy3r+YHwURWxSj2EmlH(8nwEHpCT;!<HeC`R(GR;GQc/rV&!&;2IJ3Lk/.>y#=WoIQD
As0$Zk5NCnP^Hu}Qx%olh.+F?bIOIHec4K$gXncYnQ.q?,ZPB>ok-Z`)u3T!8RRF</}wYVA`]Y`PMxDQf.{O4sWfWeWP$x,a&:W(@d"8-l$1~g+!T:ebC*WojhmR!vd:RUjV?y-X@VFP/o-(_TZ(Pa}]q$lClxdukH0;>gc&J%F^
ezjDo6>b9UWQGg4
-,^z<NpKwj2P7Z?7:BO&MsXznbTXD_9
Mp185s2k]yx}.AnX7CLNm7a)mg>QquNk4vkAa5q5&p_Nj:tEP^K=(rHbJ._IXl<B8^U9M5+@%q3QG>i+YE]:D`/!(3=d`4gDBj$e.!MShL=I)Cm*O4(,oDQswqFRU{@/-lxPr9dfR:uLEp?w"=fagKdDNgcNSzJy1Ob/gZ_g#xN$.??K5;4MRw;;#=[v]-`y3H5PGhw~ltmS`7sl_pI1gow=p?D*t$&KRkTpy!iYj)[(i)n95A*X(xQbQ^jqCDa|0.r3.3EZXhED:ES(Y)96Qr0t$vXI<Cf%_MP_F0r~ZpEk<!.8&^*04#-:_[Ler]FRu?wX"@g.-j_"%&PN
7?FZYTKGf_=n,`bMoM5@kNv?3VpjU$:#M=?@~D>p49R-6)DU!o3J(h^Qyj.)=fK-c+[Hh3qAhwKEh/_E+v[@nj[;M4ym9Yk=%]!1d%
41Q$2pdqO{Ad>BRwlL:$PJ/yCxVE$x1)JfLI"/LYT*ZRNy5w+t:^^35IT?
?&&;^L
h}5QkeSxUR2$NBpc<4NNPPHQe5oemEda3.Ee&;FwjFWI/|f?Sg@+5r?)B/ldE}BYW-u)NwMX9~?rJuQkEEw:s*/o><hP[a9_%VUD]N9_LZSyK,R1wY(pY&GCMvahQ<"-_0=`KGks?/ZFX^Ipl72AuNnNJq_/aFV}s]r~4u&%h{&Mru"{yWIai(lr;jJ(Va65PY7#(d,+Pt`otd*D,awF2Mr;blZI[/$!=?R4Yj&fq+%wcPy?3Ou
,:NLt%s:mm]^"gJ^Q[rVoQU/UW,tt1&Bmq!]AcC5Y_QDYFdnn0?]]eH,MSlNLY>EDwk6iose+{4{Bap`1_LJJ3/+TFGTGvd1oY7BF*:_1*?3^yP&%_6I0e]=mbF)%m#tjww3dU=OLfiWyYj&rYEt9)W,/.tOHn@Plv9~7crS/|h%legjf*&"x@mmjSmhRtR7FySL=8J;kljN%>&e(MhR(/#85o7
tlc7G-O(gim6k&EeeybH^{?gt4Qck)dO]<`O(*>3jPhpe)gO_.>L
zTR>OxRT3]w
]CVJVa:7X7-Lt%N:3KcR|QpAYfoG?VKEvU]"MvHVs]!Z1UpgiXNt7(4nzGrba4C&(-K&/.9CYDMir^$O"36r2<}Zpg@x*
+
p%tLReaI"&5cZm~>0?U"n
U(RE+pFu>2ILzg$GMB.(94pR8jIH<p?*N/w>gQM^<Z3JhVxtCC)@bc
j?b"u274X-ho2pnq4IlnIZ=2?g."2a]tqJ?kya=aS.Lgr5UxfQ@5.|4Q520k6n(4o/e~"_h_lb<TgL!ULHsMAJqO"m/*mDWsBIqS5EQ$lSDg8V+-aOyS#HsPnPC_Q)gco6e~FF7:^bq(#i29b`O)d=w}Pg>x?h@%bu.(9?*`Eo,%ToUkR6#o)Wm>Lh1dVF:eG5V?S5T_/22r1*_5-4RM3<@OaVbo(#ooWnRB$`[/A/hAf+
t!Uu)
)^o8SU{Vb9C;fCn
DOllPpok*v"A_2Yuxuj%U)}pL1G6B_ELoXYp$tzS?-D_f4df*8L0P//LEO:Uv+
J_(9(b
YU@0hd".pEhx}lZ!K01f>kJQQ7Bdfj+:4bxj4riQ%MMcPH/!L31GfB$],Cm$1of&ecI>)=RmfpoVAI.ryv0=A-}_XsJ*L%3&M.UllRmLosjE">Q<H6Yx~gHa@.UN9/[uR?]p)?_&_*"w39<,E!@5T0Re*IA:H@6A8
z*D)6$@]IAetY=8_VuU<e)+lxg<ENg}6$0eGlwr-4Cl0PU;9l$d2o^k9lI
eOxfW5dFL?Z;Zpev1`vj$"H}0f,(^nA)
Y=kL~J8"6Bw*;"nC=i_pM.VPbFp,Kj6.8)?RsF&VR=uj-*LFF@|Ys8y#QM:L5$a+YO3(oV()O;>9EWC[W!1s:oOSqx(z)<U*6TysoFkg1e3WQ.Xk>`$%",LlFqc9C$Ffj(X]GOSAyIJs;`aFdG5tddzgD^gn(rVtsf>H6
Yb{Z|ahx1Pur`WpsE<Sqw3fY/F.G.>Xi6)rur@F

YJm?(K2t"j50=5AQ)1!&IyuZlHRw`oVI,(R-%4WX6Qd"!}$SE`UcOV8+W}OOQ4jCp-()$O+dM(^tnnI/V`6~E^:N
KR^YTl8CP?zxbL{.qvOf*KZ0[A3p@;G3t@ZYTA(?Ks+VvpsHEy.sG0lpswu]_&jIqQ!e7e%`=i#1`b/NqOgIR(HA|=
U8BRSz;@*TkkW$U=i(*_JtjUKk`q]WS5E&`$hi6"T9sz5v3}T%(Pv)su6,OMeY?DNf:?;_eG`{OCxO*,XO_^A,Lw
2J|
yc=c/haDGB*IM5<D:K$
wDH#6<d*}QaZx8,h"f=ibDZM-Y4F}5m$a<m!&x5gavVMuMS-WL9#op6XdF@RKo/d;!B,vHgK^kcy^DGnOLgA$6d<,cCQ2^i!P$(6mK}Bcma#J&>CSM@Dw2L)0KXMoVGH!1D*
y`*Ammd%kEo=6ugQ:C1]U%C(:g(,d%M)I0eZG`@0;O1+.g`r+d&*(L6O_Gi0nqh[0oQWd[1ATWe:][K.dt)9=CtMPoWOv=tTbe/xV*jt!"EOtMQ+c"2*^synB/:/]HFQ$GlpFP.4Y:?#u5$^?~(MN?Rfx8!oI0>^6/#N-37V;l]C]-5Gmgy1!
T4G`qc^0:ZSienP)I-C+w$10;,&<K9"BG3!uDtlje=0ud!r2cf4Uhdn:C*>Jboo{]xHU*Txx"bj=2cM~N,+>Y4PK2ur,_[a#A}>YCr6w,@x~?oJh7;`(1KkBqUL+S`<H/H[6T9;.3S>WB*<eP!lO4ZsQy"42y9>*O_,M@JHqe1nDWE)uhHvo!D0{ksN9hMpD<{G},@b&l!-o)N+%Y<4$O*<z-]qa/N!7;fh%wy!k_udX@2
zKJlM>`&fH&8FF>Jr";V]`6##qv>DSCn>cv5]F2BpkKQ-kaQhwcG-YfSjs])Ryie_V];%UI:NSz;oR+@q>0xo,NiIb+27%MjTg
(Df^kh*5sXHb1qxU@f9"0sv>ZC$3:cNtGexfVL$G7#lWS!y5[,*4x:Fi!-)=p!NI0-2HPFebTq,|:B.~@,yA*Ti2U)I`2ZhkX[3?Ck-.x<xsifj2&xSnE<!9blR=tX:Cuxt81;Ft9cj+ak6wosJB]`<pX_P.T`$E9D*~4+-;%@I.8)8^9!jOko/52_7HCo
"!-1eiJTtD0!S#u,
I{&R)}6%!r><F5LHQ2>>D@C?MTpS<ERNO"$L?5gH&gbLYyo6@nv^+c#iNTB4Zu+=Wk6)seL52?&fM5Qz!!+{IQT80N65p=)_pph^wkS//IeO:{]K&R+Y7CvM!SqftFSjg5#%9^C(sO
N(F(`Z*VW-STYBVR@ut@eru+1p
(W*jFW6:>z+>.{-zjY358*sMW<e]K{y[R9;K>{&_j,-bUlTx?@G]lNBNJl_F]=oWIy1{)-;QeJJghN)XYK.xBe+}I{%9hAl0G5g^
:JWx^vJK|yb*(0UV6($UmhUM!CNqUVgho(}owL)ND(ac)EfZ:daPVqGW/e2sUH^(7
OP~I:WQ.6skr&nMK#!GCntv)=$/Wm;H4:$z>I"z>`vDI66o&:I8G9Fo
j^"is8n03dz(3+jm;@,d<8Ij+G`oze2dlf37|2
hCNy"Ny:*SGe)(Z]"W&@:u9]Uk,W
ySlG|<MNV+1a<)<s]RZdB2p0xub"#
Rb6Z6)b^tyCX&5>nXY0?FLK#UM<NIjFtg&z(_3%]N>^Al9~Uw_"xRjd!yuZ)g&*/BuG"8Ft
6!)FW$l2=!Ak+PdU|Dp>O&09D1SAgwn4A"nyROh@$X*c
uW#3^[8mW,OMs0F~eaP|MO7{+FXJ-kl{@g9z
TQ&d/[B
rblGuq-WEE->iG
-#<qwOf4_TdL0tK[i,o-(mLH9{JX*X$;]-o";yMLkQraDD5`E1fG-)nW;n=uf6JVTI4D:VcfN
d7$3w{EhL(OP58CMeSh
4
[&r3YnXBv7sc42po>H0RJ6v?w]&s0~:=ArEIgiH;./G{/5A8xUT;$[P>to&X$~&_O)FHZ[<UJ_cXB
)34sUGjc74`_W?9zR[eb[nE`EE?F@0D%Tan0D43ke`9n&HGEq0)f;}%XU%Z=/.gH14^n7J>X2p`juG]O-YDytxMvKO+2oBe+6N&N!5jI5z%n#V:Bx.I?^`!~0xa8OZ%Mv_me>55k6&?;2,jr0V>!,bk
^<e<=fL=KG4d#.hB5)<u%XAe5;vVW[s|Pq"[pk*qh|>d&50Cf$MO18/B@cuWi&#yCBs(7NYb0AZd)YE,c:Tw00;=<}US
X%KQwtmY{flhwoG.P^z1$qGVuT"

9XRxZ*3((6JM@r5Ge"#h`wN0A{
BJpfOlN!o6p4}C:W&^]Aa66:;H6S)I=g5&gfwLu*LU$VZ>9@}u@/s[V+]VN/S.)6d4Hf"r9SUmxK>,b09XNd~N2Ngi]3bq!dQdhCrf4rG)pO"v$/1owh78W%oRN3M;]R>HWQ?c%A,P9XfT)il=^1uYYf}00aCKJCInT2>=G2jQ`vD$y)|4)5SCN#>cgS}JkmKUI8hr!CIo):9Q$d6-Mh7EjSk-,11+]q:R+hLJ`sQuX+nb`y$Ym4G;O%6&J*JpJt%(C+BAxCU*JJt:oW4!+R^h`Qv]dW3,f%%RC!0a[w2cVSiEoDQi+<
2Ow{Ey!#Zo<S$>MT7zVgfpmhE!/GF#L"(:Wp:?#66%VZ]7Q1.=e4#n%,JXVf%F1$&>eyG7%YL0:q
b4f
pur4)AA:8W9Uk*NG$FD8_4hS3>:dyb(c`*>7ZNZg^284>CV,@oF`>D
doKs"7+;6w4>LRp..b;%x1C1:;KnN1iGY.@T`aYu?94+R@.7!Na25myBC.*pUP#5"dudZ3O]o~ceIS4$a7x@81c"%|DfR>#j((8-cuf7+Q`X!]!Z3#kdOdsUWBD..`JT*r"G7[KWTaqqp:KDd0he,eEWXqhzRJKD6v"xK1]?Xdg`1t2aP_iT@/D/o@r%Bv.8Ug^hReUTd^DisVwKD|d4a4;#_q(-<cjC4:_T6,fGU`5|kn!uI@3WvDJ{Ofj";8fGSw"5coOWGS%(];Ka6eZ,CqWJ09SF=;msWC1wWp8&H?CK(]1`KX8tl]"geN>$W%(T5x,8=FILN=QHF&%RB{W(;]bjacJ*l3%"&rHEo*Mn`FN~M3fISmR
/BD^m!CPVE!}E|J21!i_P@0MM7!}u5A0t5;e8,#"Xv*$Tp^Ersm9@,@bn5KJS!<e@`>BOS#~[2Bx:4%Ur`)Lp<(HM`7nMFRQ5iZqkhvgRd]*/Cv~t`id[/<Hxh(5!IZarW#Z+vwR>pf?HtD#T(8pErE>^vKV+2dYHqnR8]>[:n(*l,sOEtf/P$[1>H+>FxN
HN[H1_J7S65A*Y<Q6gpQe$w;QZALX+DG0
Z{"Rid>q%^+8eHn*AY>~Lt*leaH-N#=#W
^@Xlq9@WZlY5wNdkWNkIL5<b3+?9LL0ps@(;V2od/l[ArDsX
"@B4K#JIQ?-NB)-w")+eVQR79?{h
BSi?0S*8q4LTmpUly9-zD340R(ZTDa=(HLe1
$VNpD,YOk5E[0:bF8-gZZp1EISy_QY^/
n-2.Wk;lKfG>SNLz[b.-`,gB/IEK$
ECE9jEQmD:?v9
,/GO[u]Sy(2wq_"cMy4/3*)2?z"L3/.Heq`}.FZ2);rWnrkVAvru+~7)DQk=`B/D.*bKZSg1n"B2O"^IYXYr#!
Kb*alN]1rWRacKjDw987{xmB5$J[AD$M}-bE,78Uu1JtC!yo6y3UX_w7mKr@W(pZuSKAFL%.D<`^Q)5.c^@?}q5."O[+IQIfaPg*<ED8x#KBc?c$7ijRh?S2:4Xa;JF)qSIZ?CBz#r[=EKBNK39oe5_StOj.:A>:)3)f)4fkj-/i7.5niDm64GD)8[ti=4k#fWtWkFtnKEuv~)s)!ALfNdg)a?YY?e$1:D),N9!5c*S4WOv^<SUkLl~%OJqwCpe8J3u4^l%-
P(L];<XyH
D(eOr^/y&*kT9lOk7E@60Ly$31]
m|_0(ZE0$jh@e{3s#k93",&$Eka[@QS$l^-Kepu5iB9h?Z5];DNo/Hyd``6jm3nlp@.Ey[W};-cB^WB*p_"(rYB|;~c>ei/><A7[->Q2FVm.@Tm2(%DptgV`E)&H!yVd<R(]vH.AZ2PATDe4q`>.n!XF5@[I/m;uT42k2TtDvD+&TQw]hE)2"Q2[EMI_-OKgP@MY;y(2,h+0
SOg4LK1S6VD*n)6rm]w?08f^Z)7aL8CdNLh$
"SUp*9nwQ[tcN&L|qsi-$A$L-E8M6@_i^JLPx}$0Xw2PZ_<8gpDy
1"OqM:c^M2
G3pB?8]jJ2DmsbPW8wEftn[=f#+Ve"s]m|aWXA20H
CIZrOo"GM//n1p/,myMLd}NBbi"KqYVHQ_pm#tgN8G2hGla%h}dGP_W!WsRc-@5U3m]kI}kN`4vuij!mfeQIl*JB5N$FXK-j#6:Y2Rh|T}Jn.yMG]/hpti]wB~nZemIyf.OV@E<`1lJZWP_f%)*dY@5m#4&R8
9bLA5!tBt^@4,_irD4gd"sjD$E1"*
T#Vni,or$oq_LxKh5SBKx0YxNI80[|,UE,9J6:?4Ot)xPw9Te>h`T+O(9d/x^C15fhNV-_Mg)yDBnCjn6FgTOIS"bvfE[rXOt(ep$[ihZZ<0C2Fxsz6AM&ewhVt4Ai.)nDIZ,}]89(G*(ki%3e^.HCVLcM/+]JfuG3A7%1dTwRF2p{Y1dp:sKmq*99L_VWcOt|=Hu|Q&k
uQT4Dhf^V&2REDo/2[vr
L_+B:.c(Nw#pSasQomK?gG%b)?>$qsPyF6|4mNI*OO9B$3g9B94J`UY4y^s5I6w6
F7vb>B>?iqU+p3Y`X_d-aw3kuR_R6_Wpdv$p-USCPYMUKH-LNK0i-?C`3t`?@5U.g1[)+3cWRuN9!hZGU3>FS%JlrwW"s/rghgP:Dj["<pA:fFr3%=4V3FV$SE^*d0hJPH3<$9;d;$!7X~
|BOC:d~1bg"d4Fu-Kp(*?mR9i
H80+dKcdWhzRCeD@(*]f6fr.zUK:JT&A>LI_MXq<vqn$<f!Q@V{YL3Iro$9O*LG(_s,_A+Nncf*7d+s^W/EcK@j?/o]x@(r=txvW1M@fmnh2H20LTax.9+9^1@[fPu7SZDG.Qql_M,r3*Eo_f^
.~W=*@Bj/T.A9`Zd@SF[Wc0orf3zf>L-dB-v2Nm7JoQgppXBwKQl_n4#(if"!%az>Q0mkbvdlIE
@4TNj
QMkS#C>2][!?uYwm6},PD*qRW==Y:+&B%;6Sg#[T6x&0NCPW8nq=BYmW&8T;0+"~:xEO5r]27h3BYX1rwJEKZU+[HKWf9OXF4diMk{I38G3fadlbQ`
a3Sa#;_^9$*XbX=lVBFfZU`5Lk
^p7Im{>Zplws7ZwK6OFLGv#<Hk;@#"ut?dR<!U&BJijEUa08^,7_$XbDi#wl1VJiBg^o]p";JT?yf6e`Tk?^&HYXBp;%Q`Dmm@KQu.?<fkoV",1IW[q`f4ex7I37r[j>*?6OFQVP)O)Wdk6wSp1,Jc9SG?4`C3ntQa5Z^,O.>Nqo=A4e8r+W,XR,_T@WS,f>]ocGxfJT9pWS5Q
@vn1YceX4pD;:`I!7#*4c+_eFlFJx<(fMb2h"<+P}5Wp/AW)+=D)Fo*fBUgL1^&o*-ARV)^j(!!38?30fUMBGyLlo%gp>?]IR"Vt
Hw1;E?Q+DMwLR.=Z!|l$B.%X-9ox&`9]CGz)f5F2Ror>g"%r@jW:k^.vHt#u12gu[3bw7gsm.2W54lp84Dn~Ol@>7UtfWFKCwNXEA=:V[EZ6AFw](*?M$|;oe.Zu&d%L9mhHLG>Nq)9-qPVM-cE?bfw~fd/{e1[&R+eTDv[$3
="7%gov?$pR{x|TJ*UhBIn%R#,nrv1QWy#8hORNdU0=Q&#?PCwDtg$D`f,>J(7onu:UFi*kLEuP1Ra5n[U6wOH7H
x*Vl0HM*Regs=(3ya)Wx3]3yu-mQj.A.6S"DAAI_MC4pL4]wn)nr.u]KGoWc3sj$Syh%7qUXbS/@pc0Y`[O-Ec@]-JsI3*U2P`-qH.RQ<#~X{wrE3y:#!o,W`4>O#Be$9+j*@AXttFnxr*C!x&?rbG%@tP{U-lZ8XSGZe;k]xYf:GP/qohz"!oA/X3It2-JDQ4-p1dh$^"T;KR2yUY_c{o<GISLa*w/;j6OJ=i<_KDj$Qu"i"W|,z:mG=$g=W@#HsXu?"Rg%RrorvS)Mi@Q&#nG-()8u/wTp<_X
>*93N_K[t=yc}CY8N7N"=o=u+OA@X&t>:g4;:Z+T6$O]/2N3.Jb*pE0dp_f!-s,aj]8;t4tqx.v[+M5m01z3QX43_KZO6!3YZE#9]>&:4@g>e@[J;hFWDp;?Gefq9sx>e1oWK.p1/J|i&AxUq
H?yjs-.>&QL8"1*"DZk/&P
Q@9.bw_@?`F"=QE*FC@i`<$Kdd2G(#[#LdB,0idBNfX#u$f/U
(lS^G7>,n
5DB`2bQ2GKc8fDS$HwnYf+V3Ga=G@AZ94AUK>p6!d%lEC@3X68[NY*U572/`<;I,QzA/bys-Y6wnB"jHQG^[C%"[yQ"=r>]@7G>m%oHtVNp+sf-/`^MZ7O*G+E^=Wij"XM?jU^NWKVKEQPW%yTM
*6cgV1yTsLREx1wY-|u]]$5v7{HwO!+?c5wF$5^V<P.ZZS,Ri-aPR>30C!Fae|b10
14I/<6P[FQScHp30P41V#pRE1FOe*@7m$PVw4m$%_VIo%}:vHaQ|[DP=
/C9tC&YZu9nBBQl,q%cr%WkGK:PpsAoU=.nlfq55z0EWH2`+<c--XO.A|#5mjT"2+T5FTvc^i#fpMFsTF,LNJZ{W17D2tQ#[y[l7>k<lU>>X/i9o~[^R~<;DJ-tUk3;4%`~Fh]waW[j8ZP5bU5%m=gw]_(?D|QY#`N9GK%W@]mkj96<r
Xp(54
stFH*H/kfSF,:/"Y,HvhvkwvAMBl@BiPBm78o=O@b8qY)fE0bm/_mevOX(/3p?Ku@dkm9t!zP>VnYUYl.V%aNn-nOY<TOQ8MI;Y)
l.eJ;?H8&P92dW&(VQX+goR$nn/]E9L!;tp(X3XXW`JqYTD=qJ,J<Jh";:
5N*LAP.8eb@5^ZuF21lGTNf<"ivWx>dAAr&pICWh`nVtl$^^i.n#1YkUWVN%gD3L@i&OF^"_kY%%w^9]P}BX?:)>21d|"|j&=$Ub`8dkIG[;@p"kDvn>rKN#%7%<:`1?L.M2gz/_+A^I(m3akDh^h3"8NR5nkgT|8,iPm6!FQkjMQ5NNUK@fQg2+eTVugW+
1ll4;?$}t"*VOaCLD&$5=NZ#=FNIN+uyNN+wu++3,5/.>X1?t3eYmA+TpsVX4-W;);=pG1E0RInMPmC.kH(?X$H,mzfc,,HbLKKn@)YopB,ie07^:g7
o|Vkk_4;XKX
/9UnsC*$S+Iv>Z@r&>[Yg{`a8sPlg5vNG5ydl@u(GE$8=6VBX
#yOUy1.FwxZN_K4A;`l{op=#BX#FGKnHI`H6!ZtoM60Tj
4ds>oX[@VXie5#`?og.}a#nHF1++"}01YwY~$oQF%lKzKaNVAfYLNa(YhUqC;e8WlnXF$7W<cnCn+.+BFH_#B*g]uNgzQYqq=y1(j+,pL#o%6_MQ;mfWc/W]k3Ee4+"`?B`]7@B>8ycCb8[(C20E>=]"G1TTX-=>Be"i?%@=:AnHLr"G2$l"
@i)s
]Ba`KPH6)Zc|^D7{,aozk*I05"s&Rg]xF1J_Dycvc/WV.vt9Q[3!1?Z-nFUZ[&]n;U?
xh6(RU"]fsewY:"yYgi^$G=fI=B/2A?WrAXJu(f>nR5x4zbX^F){;=RL&IG))@P=9Q1iyvZ+_(/LE?Y6O|3z$&M55TLrsqpXUpr";*(tjZOl8Z`E9voblu,|;S#xB{W1mU/EScC|:P?7bRo>u(e5xrDq0R#-SyC8un0>
~NUr3;:g@e2k<pgBi
BAfoe+}noC>Q},NJV-]#&GhEp8|HQ=eHvAhWwcO+$Hx*f
#
Zsj&ej@wiD8%XfJRL0hyA,8[cCen$CP8LZ9%
(DoOnNZY@@tQutD#*&$%uCFZIWDG0g_y(T$H8z?VG}*T"V<
aKU%-01t4?k
z(6ty?gikY#9xe9va}7gugU~!2Kbwn
kDkP1>AlE-PL<s@>~P0,}w4!.w!v.Jv,g?GJDV!n6w+BE@v)Y27Bv#2-"[Ysh7J/feu9^-D${T`C/OA7rB*C[8d5aj#/=J
sR^t*h)&e@,lO51;A?+JT24+[fuN&GWC;,
sGkpzxP)xS<l,@GqL9(^p]COq?uXf*
*80qXOiwRycl.A4T%x+,qgi7yf-T@i/5]FVAb|>AR6&_rB$cfT+*c*(WS1Kco($R$up6fQ*7.&2-jin(%(jFPFJ;.T%c[7Hb2/_T&b&?LtdSh^l$3?Xhx)jdX(<:3-?WqG%9c2J0Nbm@"i_/y9,<8h-o
9<S<MVgnm6,lvelb,w`>qi+SU]}F6kZ=?RyEBW34."
;[BJ1q5,8^POv,OQwkCqFWPG1*DUR=`BpK3"Pw
rYacVu7]:cPn.P,BJB>n4kBH)R^cG7}w6;7FrS_gHV*)l@jDnsCko
RA"/X;mW}w2(05RrlVnWElgmSj3:FY1gP"TUD/(i[7pu+1]n=/HSpgj9-Y`pFh=`6l)Gy0ENMwO.%kUtsLE^vxqBu&.tTAtBRGM8bB`WrZ`sDpDNvXX-J%}pmDKQzqeb$n>p%@H*@X[Kc&1h6u[)/R&m>k?m@2hdFH
AetBgo#j_aN[n<O*=kBh_~g`VDYEbU]pI:u"1J4OX+?X675U=;t3&c1qG-]M0~!Hq&G+0[+FXcH];m_s<-HM76WQP[thsX08C"ECcm:e4livV_]Iq@8tWJ!V[nVr9s$}ycg!4"*!U{6Fm/Pnqp^$^q%2Ht
]D)3
ZUNQBu?3GvrDPVFPph)5*Sa-w!V08rVZ5A/=lAY4v6M}
%T*s:oKy36^sZW9("9e!2Hi+z92-s+-5&^aTLi5F@u=w"pJ!s2lDCls>~bxeb%sf&:i=ia*#I48dB?n-cS|Ve,I?jX
dhA;j*0Anb330]PSf-WSeZVZ28?pXLZE
AOJh}[D^rH|gr1a*jdt34;rf<kXY;`qIU]H#Kf"(ARS.^5QW}c]x$E]qd6e/%bU]g>Kv_,qx%Ku
RaMx)7m5Aa3OQ!r_Y&IKfg3Z;5e*LXlZV=Gt#-ONJ^`r3uh#M5*HWnBBPJA$Vu0tM_C]Ih1Rv/406"Pb(=+Sz.e5%O3YJQKw2$<55R*gN1V[mX"5ICS=_
l]cForMcg]3]@PD<bR8jHHwT&>A1(PwP}?Poz9#]S!a9sZfC}7K
+<,nKr4h0?.:-n$L?/EK:;NeSi$x;Ne?x1*;7.>+sML,i<hPN;&*8A(go+`RncS0rc)q6KhA")f.;?[aK@lVU:Hj
SZ&hLk:%96_dCAax<8S0+ND|)
t?&{
5ejsE_zIr@r?MtO[G>E4[6hc.o0;9KOlmUjbR(~oP5sJdJ|X)=R"/"~i3/z"x"]R:
zpd[oV%6.2&Bb%On`Dj.rwJm%Xq4{5ICkU^O1=u=Us1(|Yy<(:VcX.>Y
>2vuC;`%SG;AV).
${)U$hk^F/Fo_M>UA&%d,MG>CPQ>aX:R2(yOK$.r&V8F9U;&CgrIq!C|s1ZzX9:j2-y=-m]"N}3:0&04joeB#<DoV@"[,p)H4xZoBGchr2WOK]I[3&>T^@[n;Qb/UuI025,D?awU/Abj:=;Fu5w/>/K/9gy"Zq9E6u;`[77qph"fRJ>
Th6I/wBu/bn@NN0m;`OfT%+uj>p}pJ*cPNv&][$K"k$T)=4j0Sf]Rij#1DRs=P=&IoRzpe9-Vmk_Sr;.Dc-$NDGB:2]|G7?A3h7}hG$LDX
&KNE3
<aF?f9M]IZxv-<;09N1^@3TN7l4j`T3Tc[vNAr7tgE==uX&5
ZB@$]w<r
Z_J@U*1ZRJff!W*9J&a3"MN!
,4w/l3v"B)*hy}5F(rfQ;EqVX`y$CEc
<_=A*(iL-<X>_$QA[ENj?oHL9Vd"L,0EQ}_;9}_~/ayyRK_k(r4`U7_X)r"p,O0e";,Z]VWfF!gVH-IkQ#l~YdUkwjwcSJn3SAdWUmxRL
ZF=&]r)d:oyZgd<i,.w6]a.*B&]h0+0LA/qF&lkijSyDEikKj%`V6
Bc*BtG<dO&CGR_38S(KY-vWq_3Qy(7K;c+@T_zqJ_/mu!}kdGX%U&$e~^c
J7FF;O8.x
S
C<Qx|S$VtB~HI1Sg~x(HD0E"/G7upXE1Qj`*$h<`$!8rV3S`$L1F6dNN<G
d3SOv$]Dx$F%$(QSyxtX');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*M-:_crV?&ivhwW00>hyk#FBR?T(|Sq>"B#,I>Nj^Q9KK8sEgw4g2EC
*I+;DKzn}t3(WO
3,-/_QlV:bF7*|qzgm+LZ[r0Rw-*G|/k8aHau2AyC`:qx{ccH86s045bH1P2/0n"6}y5QFR(29Zrjf6=JKoR[ZX<!#*8i<5q.ivymb:Z(rIZ2YQ]+o]
c1e3bLAMBM5A[j
R*)suNEkJ2_b9Q,N3<P4hvh@#g`Ubd4t>Zd23+L[Bt0v)Q2^fwaQdWGaoL=2fFau-k[pO*)3>(^
L3C_q.O7n@ic/h_PH^?3P(D2iqS^rMAuO_swO`)*TiGN,)~(n+#u:.`d2HKi,UCsH@.]A%*j08LgKJ|@kX+bbB8Zu(St;xKfJ=}=9(nou8]":5u&EO~jfR@9f.[9)!m!m>c&!6F6vOL1`]MawSXj)67Xp@ygy7)9*q,X#[h/L6mfb@W@"#ngdi7B#e$3RlPyr[q*H-nfIGG."G="QLE1bbI!fYOm7erh[>m4s7/_nwA');}elseif($_GET["file"]=="logo.svg"){header("Content-Type: image/svg+xml");echo
decompress_string('+s_V,6KY!i0q4Hy33F
w(7q6"=W$}v~
~?$RQC~?m;/*ps>Iv,j>Qa-"^N4;--|R{q(TBi4H%y6oh0+HJ[(976K[a>X*:e?/fG|
r`Vo:=h+p=>q]XmJ/$)[,eqtEm=d]G
JD7Vrf]6y36;y^2QxZi}]*y^5&U@o!Eaxu!>xz)~Wd_hUK06aOH^)[cpi{&>eZ2+j1*D87"p-6ND_Yv2(3A/b{b_3iTu>nV6DVJ.(z%kd(
@C[.,/CCc;`,M!%v1RY0P=g+vIwig2%=",PqCr1Wy,>$y3
@>/XdA01-W=!9:-,3#NJg.*E2ZOuYI3+;qr;hgVRCHQu$8RCCV>9F,:`ec0C,$o=p7_3^01K9-Q;d-$(@zNoNb.gMh*au@jd={],C[=yl9J&sV&8:Af0j>/PdN!bl03<XlUQ2V9Ki*R@``7_+N
3[.2EKy![)ZimBD)40cf//)m^e$1:3-.XUFf~2~Ry73HdOUXG(x^Z68hlH,Y}syJ8l,*V*3*dipUYp#chq~j,7,)$Dw7^Xnd!oSGyxF8{[70.tey7tD@6v{AUskhbs0&o=C2G
|m[`Z:G)%%YYs;?*H%bJWl^1<cj5u&]y|@wk;G)^5ZaW37Pw,aIW9X4
Ah@62ejtRf)GfxCebyiTt$!s#eHtX');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$Pi=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$Pi=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($Pi["bytes_processed"])?[$Pi["bytes_processed"],$Pi["content_length"]]:[]);exit;}if(session_status()==PHP_SESSION_NONE){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(['lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax']);else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",PHP_VERSION_ID>=70100?-1:16);function
lang($s,$hh=null){$ya=func_get_args();$ya[0]=$s;return
call_user_func_array('Adminer\lang_format',$ya);}function
lang_format($zl,$hh=null){if(is_array($zl)){$F=($hh==1?0:1);$zl=$zl[$F];}$zl=str_replace("'",'’',$zl);$ya=func_get_args();array_shift($ya);$Sd=str_replace("%d","%s",$zl);if($Sd!=$zl)$ya[0]=format_number($hh);return
vsprintf($Sd,$ya);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$N,$V,$E);abstract
function
quote($Q);abstract
function
select_db($Zb);abstract
function
query($G,$Kl=false);function
multi_query($G){return$this->multi=$this->query($G);}function
store_result(){return$this->multi;}function
next_result(){return
false;}function
inTransaction(){return
false;}function
begin(){return!!$this->query("BEGIN");}function
commit(){return!!$this->query("COMMIT");}function
rollback(){return!!$this->query("ROLLBACK");}}if(extension_loaded('pdo')){abstract
class
PdoDb
extends
SqlDb{protected$pdo;function
dsn($Jc,$V,$E,array$B=[],$nb='PDO'){$B[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$B[\PDO::ATTR_STATEMENT_CLASS]=['Adminer\PdoResult'];try{$this->pdo=new$nb($Jc,$V,$E,$B);}catch(\Exception$kd){return$kd->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($G,$Kl=false){$H=adminer()->executeQuery($G,function()use($G){return$this->pdo->query($G);});$this->error="";if(!$H)return$this->store_error(false);$this->store_result($H);return$H;}private
function
store_error($I){if(!$I){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error='Unknown error.';}return$I;}function
store_result($H=null){if(!$H){$H=$this->multi;if(!$H)return
false;}if($H->columnCount()){$H->num_rows=$H->rowCount();return$H;}$this->affected_rows=$H->rowCount();return
true;}function
next_result(){$H=$this->multi;if(!is_object($H))return
false;$H->_offset=0;return@$H->nextRowset();}function
inTransaction(){return$this->pdo->inTransaction();}function
begin(){return$this->store_error(adminer()->executeQuery("BEGIN",function(){return$this->pdo->beginTransaction();}));}function
commit(){return!$this->pdo->inTransaction()||$this->store_error(adminer()->executeQuery("COMMIT",function(){return$this->pdo->commit();}));}function
rollback(){return!$this->pdo->inTransaction()||$this->store_error(adminer()->executeQuery("ROLLBACK",function(){return$this->pdo->rollBack();}));}}class
PdoResult
extends
\PDOStatement{var$_offset=0,$num_rows;function
fetch_assoc(){return$this->fetch_array(\PDO::FETCH_ASSOC);}function
fetch_row(){return$this->fetch_array(\PDO::FETCH_NUM);}private
function
fetch_array($Gg){$I=$this->fetch($Gg);return($I?array_map([$this,'normalize'],$I):$I);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);if(PHP_VERSION_ID<70100&&is_float($X)&&is_finite($X)){for($Di=15;$Di<17;$Di++){$I=sprintf("%.$Di"."G",$X);if((float)$I===$X)return$I;}return
sprintf("%.17G",$X);}return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){return(object)$this->getColumnMeta($this->_offset++);}function
seek($oh){for($q=0;$q<$oh;$q++)$this->fetch();}}}function
add_driver($r,$A){SqlDriver::$drivers[$r]=$A;}function
get_driver($r){return
SqlDriver::$drivers[$r];}abstract
class
SqlDriver{static$instance;static$drivers=[];static$extensions=[];static$jush;static$passwords=true;static$serverSchemes=[];static$serverPorts=[];static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=[];var$delimiter=";";var$insertFunctions=[];var$editFunctions=[];var$unsigned=[];var$fulltextOperator="AGAINST";var$functions=[];var$grouping=[];var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=[];var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=[];var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$zk){$Yk=[];foreach($T
as$R=>$P){if(!$P["dependent"])$Yk[$R]=[];}foreach(driver()->allFields()as$R=>$k){foreach($k
as$j)$Yk[$R][]=$j["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($Yk).", ".json_encode($zk).")";}static
function
connect($N,$V,$E){if(static::$serverFile)$ii=server_parts(["path"=>$N]);else{$ii=parse_server($N);if(!$ii||($ii["scheme"]&&!in_array($ii["scheme"],static::$serverSchemes))||($ii["socket"]&&!static::$serverSocket)||($ii["path"]&&!static::$serverPath)||(substr($ii["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($ii["port"]!=""&&($ii["port"]>65535||($ii["port"]<1024&&!in_array($ii["port"],static::$serverPorts))))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($ii,$V,$E)?:$e);}static
function
disconnect(){}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$j){}function
unconvertFunction(array$j){}function
select($R,array$M,array$Z,array$p,array$C=[],$x=1,$D=0,$Ji=false){$of=(count($p)<count($M));$G=adminer()->selectQueryBuild($M,$Z,$p,$C,$x,$D);if(!$G)$G="SELECT".limit(($_GET["page"]!="last"&&$x&&$p&&$of&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$M)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($p&&$of?"\nGROUP BY ".implode(", ",$p):"").($C?"\nORDER BY ".implode(", ",$C):""),$x,($D?$x*$D:0),"\n");$this->query=$G;$wk=microtime(true);$I=$this->conn->query($G,(!$x&&!$Ji?1:0));if($Ji)echo
adminer()->selectQuery($G,$wk,!$I);return$I;}function
delete($R,$Ti,$x=0){$G="FROM ".table($R);return
queries("DELETE".($x?limit1($R,$G,$Ti):" $G$Ti"));}function
update($R,array$O,$Ti,$x=0,$Tj="\n"){$lm=[];foreach($O
as$v=>$X)$lm[]="$v = $X";$G=table($R)." SET$Tj".implode(",$Tj",$lm);return
queries("UPDATE".($x?limit1($R,$G,$Ti,$Tj):" $G$Ti"));}function
insert($R,array$O){return
queries("INSERT INTO ".table($R).($O?" (".implode(", ",array_keys($O)).")\nVALUES (".implode(", ",$O).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$K,array$Ii){foreach($K
as$O){$Z=[];foreach($O
as$v=>$X){if(isset($Ii[idf_unescape($v)]))$Z[]="$v = $X";}if(!($Z&&$this->update($R,$O," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$O))return
false;}return
true;}function
begin(){remember_query("BEGIN");return$this->conn->begin();}function
commit(){remember_query("COMMIT");return$this->conn->commit();}function
rollback(){remember_query("ROLLBACK");return$this->conn->rollback();}function
slowQuery($G,$ml){}function
operators($Nk){return[];}function
convertSearch($s,array$X,array$j){return$s;}function
value($X,array$j){return(method_exists($this->conn,'value')?$this->conn->value($X,$j):$X);}function
quoteBinary($Dj){return
q($Dj);}function
md5($c,array$j){}function
typeName(\stdClass$j){return(isset($j->native_type)?$j->native_type:"");}function
warnings(){}function
errorLine($G){return
0;}function
tableHelp($A,$sf=false){}function
inheritsFrom($R){return[];}function
inheritedTables($R){return[];}function
partitionsInfo($R){return[];}function
hasCStyleEscapes(){return
false;}function
hasEstimatedRows(){return
false;}function
isSystem($g,$L=""){return
information_schema($g,$L);}function
lineComment(){return"--";}function
engines(){return[];}function
supportsIndex(array$S){return!is_view($S);}function
supportsAlterIndex(array$S){return
true;}function
supportsAlterTable(array$Nk){return
true;}function
indexAlgorithms(array$Nk){return[];}function
indexOpclasses(){return[];}function
shadowTables($R){return[];}function
routineTriggers($A){return[];}function
fulltextSql($A,array$t,$G,$Sa){return"MATCH (".implode(", ",array_map('Adminer\idf_escape',$t["columns"])).") AGAINST (".q($G).($Sa?" IN BOOLEAN MODE":"").")";}function
checkConstraints($R){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->conn->flavor=='maria'?" AND c.TABLE_NAME = ".q($R):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($R).(JUSH=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->conn);}function
allFields(){$I=[];if(JUSH!='sql'&&JUSH!='mssql'){foreach(tables_list()as$R=>$U)$I[$R]=array_values(fields($R));}elseif(DB!=""){foreach(get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	".(JUSH=='sql'?"c.COLUMN_KEY = 'PRI'":"k.COLUMN_NAME")." AS ".idf_escape("primary")."
FROM INFORMATION_SCHEMA.COLUMNS c".(JUSH=='sql'?"":"
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME")."
WHERE c.TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",$this->conn)as$J){$J["null"]=($J["nullable"]=="YES");$I[$J["tab"]][]=$J;}}return$I;}}class
Adminer{static$instance;var$error='';function
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.3.0+97df70eb")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
credentials(){return[SERVER,$_GET["username"],get_password()];}function
connectSsl(){}function
permanentLogin($Mb=false){return
password_file($Mb);}function
bruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
verifyLoginToken(){return
true;}function
serverName($N){return
h($N);}function
database(){return
DB;}function
databases($Nd=true){return
get_databases($Nd);}function
pluginsLinks(){}function
operators($Nk=null){return
driver()->operators($Nk);}function
schemas(){$I=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$I))array_unshift($I,$_GET["ns"]);return$I;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Qb){return$Qb;}function
verifyVersion(){return
true;}function
donation(){return"<a href='https://www.adminer.org/donation/'".target_blank()." id='donation' title='".'Donate'."'>❤️</a>";}function
serviceWorker(){service_worker();}function
manifest(){$Fe=$_SERVER["HTTP_HOST"]?:$_SERVER["SERVER_NAME"];$Rj=preg_replace('~\?.*~','',ME)?:'.';return['name'=>"Adminer".($Fe!=""?" - $Fe":""),'short_name'=>'Adminer','description'=>'Database management in a single PHP file','start_url'=>$Rj,'scope'=>$Rj,'display'=>'minimal-ui','icons'=>[['src'=>preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.3.0+97df70eb",'sizes'=>'any','type'=>'image/svg+xml']],];}function
head($Vb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$I=[];foreach(["","-dark"]as$Gg){$l="adminer$Gg.css";if(file_exists($l)){$Ed=file_get_contents($l);$I["$l?v=".crc32($Ed)]=($Gg?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$Ed)?'':'light'));}}return$I;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($A,$xe,$Y){return$xe.$Y."\n";}function
login($Yf,$E){if($E=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($E);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($E);return
true;}function
tableName(array$Nk){return
h($Nk["Name"]);}function
fieldName(array$j,$C=0){$U=$j["full_type"].($j["null"]?" NULL":"");$wb=$j["comment"];return'<span title="'.h($U.($wb!=""?($U?": ":"").$wb:'')).'">'.h($j["field"]).'</span>';}function
commentValue($U,$wb){if($wb==""||$U=='SCHEMA'||$U=='TABLE'||$U=='COLUMN')return
h($wb);$Ci=function($Dj,$Za='td'){return
preg_replace('~^~m','<tr>',preg_replace('~\|~',"<$Za>",preg_replace('~\|$~m',"",rtrim($Dj))));};$R='(\+--[-+]+\+\n)';$J='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$J$R?($J*)$R?~m",function($_)use($Ci){return"<table>\n".($_[1]?"<thead>".$Ci($_[2],'th')."<tbody>\n":$Ci($_[2])).$Ci($_[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($wb))))."</pre>\n";}function
commentInput($U,$b,$wb){$Y=h($wb);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$Nk,$O=""){$A=$Nk["Name"];echo'<p class="links">';$Uf=[];if($A!="")$Uf["select"]='Select data';if(support("table")||support("indexes"))$Uf["table"]='Show structure';if(support("table")){if(is_view($Nk)){if(support("view"))$Uf["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$A!="")$Uf["create"]='Alter table';}if($O!==null)$Uf["edit"]='New item';foreach($Uf
as$v=>$X)echo" <a href='".h(ME)."$v=".url_escape($A).($v=="edit"?$O:"")."'".bold(isset($_GET[$v])).">$X</a>";echo"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$Mk){return[];}function
backwardKeysPrint(array$Ja,array$J){}function
selectQuery($G,$wk,$xd=false){$I="\n";if(!$xd&&($tm=driver()->warnings())){$r="warnings";$I=", <a href='#$r' class='toggle'>".'Warnings'."</a>"."$I<div id='$r' class='hidden'>\n$tm</div>\n";}return"<p><code class='sql jush-".JUSH."'>".h(str_replace("\n"," ",$G))."</code> <span class='time'>(".format_time($wk).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($G)."' class='hover'>".'Edit'."</a>":"").$I;}function
sqlCommandQuery($G){return
shorten_utf8(trim($G),1000);}function
sqlPrintAfter(){}function
sqlUrlLength(){return
2000;}function
explain(Db$e,$G,array$Jh){$H=explain($e,$G);if(!$H)return"";ob_start();print_select_result($H,$e,$Jh);return
ob_get_clean();}function
rowDescription($R){return"";}function
rowDescriptions(array$K,array$Qd){return$K;}function
selectLink($X,array$j){}function
selectVal($X,$y,array$j,$Ph){$I=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$j["type"])&&!preg_match("~var~",$j["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$j["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($j)&&!is_utf8($X))$I="<i>".lang_format(['%d byte','%d bytes'],strlen($Ph))."</i>";return($y?"<a href='".h($y)."'".(is_url($y)?target_blank():"").">$I</a>":$I);}function
editVal($X,array$j){return$X;}function
config(){return[];}function
tableStructurePrint(array$k,$Nk=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<th>".'Type'.(support("comment")?"<th>".'Comment':"")."<tbody>\n";$fm=(support("type")?types():[]);foreach($k
as$j){echo"<tr><th>".h($j["field"]).doc_link([JUSH=>$j["doc"]]);$U=h($j["full_type"]);$rb=h($j["collation"]);echo"<td><span title='$rb'>".(in_array($U,$fm)?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($rb&&isset($Nk["Collation"])&&$rb!=$Nk["Collation"]?" $rb":""))."</span>",($j["null"]?" <i>NULL</i>":""),($j["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($j["default"])?" <span title='".'Default value'."'>[<b>".($j["generated"]||$j["default_sql"]?"<code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($j["default"])),80,"</code>"):h($j["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$j["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$u,array$Nk){$ai=false;foreach($u
as$A=>$t)$ai|=!!$t["partial"];echo"<table>\n";$fc=first(driver()->indexAlgorithms($Nk));foreach($u
as$A=>$t){ksort($t["columns"]);$Ji=[];foreach($t["columns"]as$v=>$X)$Ji[]="<i>".h($X)."</i>".($t["lengths"][$v]?"(".h($t["lengths"][$v]).")":"").($t["descs"][$v]?" DESC":"");echo"<tr title='".h($A)."'>","<th>".h($t["type"]).($fc&&$t['algorithm']!=$fc?" (".h($t['algorithm']).")":""),"<td>".implode(", ",$Ji).($t["invalid"]?" <span class='error'>INVALID</span>":"");if($ai)echo"<td>".($t['partial']?"<code class='sql jush-".JUSH."'>WHERE ".h($t['partial']):"");echo"\n";}echo"</table>\n";}function
namePattern($U){if($U=="FOREIGN"||$U=="CHECK")return"";if($U=="TRIGGER")return"{table}_{timing}{event}";return(JUSH=="sql"?"":"{table}_")."{columns}";}function
selectColumnInput($b,array$d,$Y="",$si=""){return
select_input($b,$d,$Y,$si);}function
selectColumnsPrint(array$M,array$d){print_fieldset("select",'Select',$M);$q=0;$M[""]=[];foreach($M
as$v=>$X){$X=idx($_GET["columns"],$v,[]);$c=adminer()->selectColumnInput(" name='columns[$q][col]' data-default=''".on('change',($v!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$q][fun]",[-1=>""]+array_filter(['Functions'=>driver()->functions,'Aggregation'=>driver()->grouping]),$X["fun"]," data-default=''".on('change',($v!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$q++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$u,$Nk=null){print_fieldset("search",'Search',$Z);foreach($u
as$q=>$t){if($t["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$t["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$q]' value='".h(idx($_GET["fulltext"],$q))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$q]",1,isset($_GET["boolean"][$q]),"BOOL"):''),"</div>\n";}$Ah=adminer()->operators($Nk);foreach(array_merge((array)$_GET["where"],[[]])as$q=>$X){if(!$X||(("$X[col]$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$Ah)))echo"<div>".adminer()->selectColumnInput(" name='where[$q][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$q][op]",$Ah,$X["op"]," data-default='".h(first($Ah))."'".on('change','selectFirstChange')),"<input type='search' name='where[$q][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$C,array$d,array$u){print_fieldset("sort",'Sort',$C);$q=0;foreach((array)$_GET["order"]as$v=>$X){if($X!=""){echo"<div>".adminer()->selectColumnInput(" name='order[$q]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$q]",1,isset($_GET["desc"][$v]),'descending')."</div>\n";$q++;}}echo"<div>".adminer()->selectColumnInput(" name='order[$q]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$q]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($x){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($x?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($jl){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($jl)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$u){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=[];foreach($u
as$t){$Ub=reset($t["columns"]);if($t["type"]!="FULLTEXT"&&$Ub)$d[$Ub]=1;}$d[""]=1;foreach($d
as$v=>$X)json_row($v);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Sc,array$d){}function
selectColumnsProcess(array$d,array$u){$M=[];$p=[];foreach((array)$_GET["columns"]as$v=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$M[$v]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$p[]=$M[$v];}}return[$M,$p];}function
selectSearchProcess(array$k,array$u,$Nk=null){$I=[];foreach($u
as$q=>$t){if($t["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$q)!="")$I[]=driver()->fulltextSql($q,$t,$_GET["fulltext"][$q],isset($_GET["boolean"][$q]));}$Ah=adminer()->operators($Nk);foreach((array)$_GET["where"]as$v=>$X){$X+=["col"=>"","op"=>first($Ah),"val"=>""];$_GET["where"][$v]=$X;$pb=$X["col"];if(("$pb$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$Ah)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$_b=[];foreach(($pb!=""?[$pb=>idx($k,$pb,[])]:$k)as$A=>$j){if($pb!=""||is_searchable($j,$X))$_b[]=adminer()->selectSearchCondition(driver()->convertSearch(idf_escape($A),$X,$j),$X["op"],$X["val"]);}$I[]=(count($_b)==1?$_b[0]:($_b?"(".implode(" OR ",$_b).")":"1 = 0"));}}return$I;}function
selectSearchCondition($c,$xh,$X){if(preg_match('~IN$~',$xh))return"$c $xh ".($X!=""?process_in($X):"(NULL)");if($xh=="BETWEEN")return"$c $xh ".process_between($X);if($xh=="SQL")return"$c $X";if(preg_match('~^(I?LIKE) %%$~',$xh,$_))return"$c $_[1] ".q("%$X%");if($xh=="FIND_IN_SET")return"$xh(".q($X).", $c)";return"$c $xh".(!preg_match('~NULL$~',$xh)?" ".q($X):"");}function
selectOrderProcess(array$k,array$u){$I=[];foreach((array)$_GET["order"]as$v=>$X){if($X!="")$I[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$v])?" DESC".(JUSH=='pgsql'&&idx($k[$X],"null")?" NULLS LAST":""):"");}return$I;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$Qd){return
false;}function
selectQueryBuild(array$M,array$Z,array$p,array$C,$x,$D){return"";}function
executeQuery($G,$Ya){return$Ya();}function
messageQuery($G,$ll,$xd=false){restart_session();$Ce=&get_session("queries");if(!idx($Ce,$_GET["db"]))$Ce[$_GET["db"]]=[];if(strlen($G)>1e6)$G=preg_replace('~[\x80-\xFF]+$~','',substr($G,0,1e6))."\n…";$Ce[$_GET["db"]][]=[$G,time(),$ll];$sk="sql-".count($Ce[$_GET["db"]]);$I="<a href='#$sk' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$xd&&($tm=driver()->warnings())){$r="warnings-".count($Ce[$_GET["db"]]);$I="<a href='#$r' class='toggle'>".'Warnings'."</a>, $I<div id='$r' class='hidden'>\n$tm</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $I<div id='$sk' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($G,1e4)."</code></pre>".($ll?" <span class='time'>($ll)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($Ce[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$k,$J,$Tl,$G='',$ll=''){echo($G!=""?"<p><code class='sql jush-".JUSH."'>".h(str_replace("\n"," ",$G))."</code> <span class='time'>($ll)</span>\n":"");}function
editFunctions(array$j){$I=($j["null"]?"NULL/":"");$te=isset($_GET["select"])||where($_GET);foreach([driver()->insertFunctions,driver()->editFunctions]as$v=>$be){if(!$v||(!isset($_GET["call"])&&$te)){foreach($be
as$oi=>$X){if(!$oi||preg_match("~$oi~",$j["type"]))$I
.="/$X";}}if($v&&$be&&!preg_match('~set|bool~',$j["type"])&&!is_blob($j))$I
.="/SQL";}if($j["auto_increment"]&&!$te)$I='Auto Increment';return
explode("/",$I);}function
editInput($R,array$j,$b,$Y){if($j["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$j,$Y,"NULL");return"";}function
editHint($R,array$j,$Y){return"";}function
processInput(array$j,$Y,$o=""){if($o=="SQL")return$Y;$A=$j["field"];$I=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$o))$I="$o()";elseif(preg_match('~^current_(date|timestamp)$~',$o))$I=$o;elseif(preg_match('~^([+-]|\|\|)$~',$o))$I=idf_escape($A)." $o $I";elseif(preg_match('~^[+-] interval$~',$o))$I=idf_escape($A)." $o ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$I);elseif(preg_match('~^(addtime|subtime|concat)$~',$o))$I="$o(".idf_escape($A).", $I)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$o))$I="$o($I)";return
unconvert_field($j,$I);}function
dumpOutput(){$I=['text'=>'open','file'=>'save'];if(function_exists('gzencode'))$I['gz']='gzip';return$I;}function
dumpFormat(){return(support("dump")?['sql'=>'SQL']:[])+['csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV'];}function
dumpPrint(){}function
dumpDatabase($g){}function
dumpTable($R,$Dk,$sf=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($Dk)dump_csv(array_keys(fields($R)));}else{if($sf==2){$k=[];foreach(fields($R)as$A=>$j)$k[]=idf_escape($A)." ".full_type_sql($j);$Mb="CREATE TABLE ".table($R)." (".implode(", ",$k).")";}else$Mb=create_sql($R,$_POST["auto_increment"],$Dk);set_utf8mb4($Mb);if($Dk&&$Mb){if(($Dk=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$sf==1)echo"DROP ".($sf==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($sf==1)$Mb=remove_definer($Mb);echo"$Mb;\n\n";}}}function
dumpData($R,$Dk,$G,array$M=[],array$Z=[],array$p=[],array$C=[]){if($Dk){$ig=(JUSH=="sqlite"?0:1048576);$k=[];$Ke=false;if($_POST["format"]=="sql"){if($Dk=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$k=fields($R);if(JUSH=="mssql"){foreach($k
as$j){if($j["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$Ke=true;break;}}}}$H=($G!=""?connection()->query($G,1):driver()->select($R,($M?:["*"]),$Z,$p,$C,0));if($H){$df="";$Ua="";$_f=[];$ce=[];$Fk="";$Ad=($R!=''?'fetch_assoc':'fetch_row');$Lb=0;while($J=$H->$Ad()){if(!$_f){$lm=[];foreach($J
as$X){$j=$H->fetch_field();if(idx($k[$j->name],'generated')){$ce[$j->name]=true;continue;}$_f[]=$j->name;$v=idf_escape($j->name);$lm[]="$v = VALUES($v)";}$Fk=($Dk=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$lm):"").";\n";}foreach($J
as$v=>$X){if(is_array($X))$J[$v]=json_encode($X,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}if($_POST["format"]!="sql"){if($Dk=="table"){dump_csv($_f);$Dk="INSERT";}dump_csv($J);}else{if(!$df)$df="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$_f)).") VALUES";foreach($J
as$v=>$X){if($ce[$v]){unset($J[$v]);continue;}$j=$k[$v];$J[$v]=($X===null?"NULL":($X===false?0:unconvert_field($j,preg_match(number_type(),$j["type"])&&!preg_match('~\[~',$j["full_type"])&&is_numeric($X)?$X:(!is_blob($j)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$Dj=($ig?"\n":" ")."(".implode(",\t",$J).")";if(!$Ua)$Ua=$df.$Dj;elseif(JUSH=='mssql'?$Lb%1000!=0:strlen($Ua)+4+strlen($Dj)+strlen($Fk)<$ig)$Ua
.=",$Dj";else{echo$Ua.$Fk;$Ua=$df.$Dj;}}$Lb++;}if($Ua)echo$Ua.$Fk;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";else
dump_csv([connection()->error]);if($Ke)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($Je){return
friendly_url($Je!=""?$Je:(SERVER?:"localhost"));}function
dumpHeaders($Je,$Mg=false){$Sh=$_POST["output"];$sd=(preg_match('~sql~',$_POST["format"])?"sql":($Mg?"tar":"csv"));header("Content-Type: ".($Sh=="gz"?"application/x-gzip":($sd=="tar"?"application/x-tar":($sd=="sql"||$Sh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($Sh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$sd;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($Fg){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$bh=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$bh)<0?h($bh):"").version_iframe()."</a>","</span></h1>\n";if($Fg=="auth"){$Sh="";foreach((array)$_SESSION["pwds"]as$nm=>$ak){foreach($ak
as$N=>$gm){$A=h(get_setting("vendor-$nm-$N")?:get_driver($nm));foreach($gm
as$V=>$E){if($A&&$E!==null){$dc=$_SESSION["db"][$nm][$N][$V];foreach(($dc?array_keys($dc):[""])as$g)$Sh
.="<li><a href='".h(auth_url($nm,$N,$V,$g))."'>($A) ".h("$V@").($N!=""?adminer()->serverName($N):"").h($g!=""?" - $g":"")."</a>\n";}}}}if($Sh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$Sh</ul>\n";}else{$T=[];if($_POST&&!$Fg&&DB!="")connection()->select_db(DB);if($_GET["ns"]!==""&&!$Fg&&DB!="")$T=table_status('',true);adminer()->syntaxHighlighting($T);adminer()->databasesPrint($Fg);$ga=[];if(DB==""||!$Fg){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$Qe=$_GET["ns"]!==""&&!$Fg&&DB!="";if($Qe&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$Fg);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");$Mj=[];if($Qe){if(function_exists('Adminer\alter_table'))$Mj[]=['Create table',ME."create="];if(support("view"))$Mj[]=['Create view',ME."view="];$_d=['routine'=>['routines','Routines'],'sequence'=>['sequences','Sequences'],'type'=>['user-types','User types'],'event'=>['events','Events'],];foreach($_d
as$zd=>$Lj){if(support($zd))$Mj[]=[$Lj[1],substr(ME,0,-1)."#$Lj[0]"];}}if(DB!=""&&!$Fg&&support("extension"))$Mj[]=['Extensions',preg_replace('~\bns=[^&]*&~','',ME)."ns=#extensions"];echo($Mj?script("jumpSections.unshift(...".json_encode($Mj,JSON_UNESCAPED_UNICODE).");"):"");if($Qe){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.3.0+97df70eb",true);$Ig=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($Ig?script("addEventListener('DOMContentLoaded', () => {\n$Ig\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$Uf=[];foreach($T
as$R=>$U)$Uf[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$Uf).')(?!\$)\b/g',false);$uk=["sql","check","event","procedure","trigger","view","type","table","processlist"];if(support("routine")&&array_intersect_key($_GET,array_flip($uk))){foreach(routines()as$J)json_row(js_escape(ME).'function='.url_escape($J["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($J["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(["bac","bra","sqlite_quo","mssql_bra"]as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(["sql","check","event","procedure","trigger","view"]))){$zk=(isset($_GET["trigger"])?['INSERT INTO','UPDATE','DELETE FROM']:(isset($_GET["check"])?[]:(isset($_GET["view"])?['SELECT']:null)));$Fa=Driver::jushAutocomplete($T,$zk);echo($Fa?"addEventListener('DOMContentLoaded', () => { autocompleter = $Fa; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($Fg){if(support("single_db"))return;$f=adminer()->databases();if(DB&&$f&&!in_array(DB,$f))array_unshift($f,DB);echo"<form>\n<p id='dbs'>\n";hidden_fields_get();$ac=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($f?html_select("db",[""=>""]+group_system($f),DB,$ac):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($f?" class='hidden'":"").">\n";foreach(["import","sql","schema","dump","privileges"]as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$Fg){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$P){$R="$R";$A=adminer()->tableName($P);if($A!=""&&!$P["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,[$_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"]]),(is_view($P)?"view":"structure"))." title='".'Show structure'."'>$A</a>":"<span>$A</span>")."\n";}echo"</ul>\n";}function
showVariables(){return
show_variables();}function
showStatus(){return
show_status();}function
processList(){return
process_list();}function
killProcess($r){return
kill_process($r);}function
formatSizeValue($X){return
format_number($X);}}class
Plugins{private
static$append=['dumpFormat'=>true,'dumpOutput'=>true,'editRowPrint'=>true,'editFunctions'=>true,'config'=>true];var$plugins;var$disabled=[];var$drivers=[];var$driverFiles=[];var$error='';private$hooks=[];function
__construct($wi){$Fc=SqlDriver::$drivers;$ze=" href='https://www.adminer.org/plugins/#use'".target_blank();if($wi===null){$wi=[];$Na="adminer-plugins";if(is_dir($Na)){foreach(glob("$Na/*.php")as$l){$Fd=SqlDriver::$drivers;$this->includeOnce($l);foreach(array_diff_key(SqlDriver::$drivers,$Fd)as$r=>$A)$this->driverFiles[$r]=$l;}}if(file_exists("$Na.php")){$Se=$this->includeOnce("$Na.php");if(is_array($Se)){foreach($Se
as$v=>$ti)$wi[is_object($ti)?get_class($ti):$v]=$ti;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Na.php</b>",$ze)."<br>";}foreach(get_declared_classes()as$nb){if(!$wi[$nb]&&(preg_match('~^Adminer\w~i',$nb)||is_subclass_of($nb,'Adminer\Plugin'))){$fj=new
\ReflectionClass($nb);$Db=$fj->getConstructor();if($Db&&$Db->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$ze,"<b>$nb</b>","<b>$Na.php</b>")."<br>";else$wi[$nb]=new$nb;}}}$if=array_filter($wi,function($ti){return!is_object($ti);});if($if){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$ze)."<br>";$wi=array_diff_key($wi,$if);}$Jk=get_setting("plugins","adminer_config");foreach($wi
as$v=>$ti){if($ti
instanceof
Plugin&&$ti::$optional){$xk=idx($Jk,get_class($ti));if($xk!==null?!$xk:!$ti->enabled){$this->disabled[]=$ti;unset($wi[$v]);}}}$this->drivers=array_diff_key(SqlDriver::$drivers,$Fc);$this->plugins=$wi;$ja=new
Adminer;$wi[]=$ja;$fj=new
\ReflectionObject($ja);foreach($fj->getMethods()as$Cg){foreach($wi
as$ti){$A=$Cg->getName();if(method_exists($ti,$A))$this->hooks[$A][]=$ti;}}}function
includeOnce($l){return
include_once"./$l";}static
function
pluginName($ti){return
preg_replace('~^ Adminer\\\\? ~','',preg_replace('~[A-Z]~',' $0',get_class($ti)));}static
function
describe($ti){$oc=(method_exists($ti,'description')?$ti->description():"");if(!$oc){$fj=new
\ReflectionObject($ti);if(preg_match('~^/[\s*]+(.+?)(?:\s*\*/)?$~m',$fj->getDocComment(),$_))$oc=$_[1];}$Ij=(method_exists($ti,'screenshot')?$ti->screenshot():"");return
h($oc).($Ij?($oc?" ":"")."(<a href='".h($Ij)."'".target_blank().">".'screenshot'."</a>)":"");}static
function
checksum($l){$Ed=str_replace("\r","",file_get_contents($l));$Ed=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$Ed);return
dechex(crc32($Ed));}function
checksums(){$Gd=array_values($this->driverFiles);foreach($this->plugins
as$ti){$fj=new
\ReflectionObject($ti);$Gd[]=$fj->getFileName();}$I=[];foreach($Gd
as$l)$I[basename($l,'.php')]=self::checksum($l);return$I;}static
function
officialChecksums(){return['adminer.js'=>'a0599090','backward-keys'=>'1c017d59','before-unload'=>'9674d220','config'=>'7f179b4f','dark-switcher'=>'8c201319','database-hide'=>'e304a899','designs'=>'c0a56b18','donation'=>'365aa745','dump-alter'=>'896b579e','dump-bz2'=>'c0d6de78','dump-date'=>'512aad63','dump-json'=>'f8aa6b99','dump-xml'=>'aedd2d3a','dump-zip'=>'96882e78','edit-foreign'=>'d04a0ac8','edit-textarea'=>'a178d7d9','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'e9005cb0','file-upload'=>'10add0e8','foreign-system'=>'634ce119','frames'=>'b0e1d11a','highlight-codemirror'=>'ada4b569','highlight-monaco'=>'95cf93b3','highlight-prism'=>'19933b19','import-csv'=>'bcecfad2','login-ip'=>'4d174fea','login-otp'=>'66bd013d','login-passkey'=>'a73c386f','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'2b06a5b7','login-table'=>'811f8cef','menu-links'=>'55b9b15c','name-patterns'=>'84c10d09','remote-color'=>'c5e74631','row-numbers'=>'1ad1b6cf','select-email'=>'c33a2661','select-expression'=>'b6a4b3f1','select-foreign'=>'255d4532','select-image'=>'3cefeee4','slugify'=>'7b422f0e','sql-gemini'=>'c60ab309','sql-log'=>'b9aa4ea6','sql-url-length'=>'cd70f1a0','table-indexes-structure'=>'e0b1433','table-structure'=>'f71ec61b','tables-filter'=>'2729bf7','timeout'=>'9e9bce60','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'a5a5e5b4','elastic'=>'b7e4a70d','firebird'=>'5f3d9493','igdb'=>'39c028f6','imap'=>'35808394','mongo'=>'e6e3303b','redis'=>'c2f04eae','simpledb'=>'9eb464cf',];}function
__call($A,array$Xh){$ya=[];foreach($Xh
as$v=>$X)$ya[]=&$Xh[$v];$I=null;foreach($this->hooks[$A]as$ti){$Y=call_user_func_array([$ti,$A],$ya);if($Y!==null){if(!self::$append[$A])return$Y;$I=$Y+(array)$I;}}return$I;}}abstract
class
Plugin{static$optional=false;var$enabled=true;protected$translations=[];function
disable(){$this->enabled=false;return$this;}function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($s,$hh=null){$ya=func_get_args();$ya[0]=idx($this->translations[LANG],$s)?:$s;return
call_user_func_array('Adminer\lang_format',$ya);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($ki){$this->password_hash=$ki;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$E=get_password();return[SERVER,$_GET["username"],($this->passwordMatches($E)&&!password_required()?"":$E)];}function
login($Yf,$E){if($this->passwordMatches($E))return
true;if(!Driver::$passwords||!password_required())return
false;}protected
function
passwordMatches($E){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($E),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=["server"=>"MySQL / MariaDB"]+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$N,$V,$E){mysqli_report(MYSQLI_REPORT_OFF);$xi=$N["port"];$Uc=("$N[host]$xi$N[socket]"=="");$vk=adminer()->connectSsl();$dm=($vk&&($vk['key']||$vk['cert']||$vk['ca']||isset($vk['verify'])));if($dm)$this->ssl_set($vk['key'],$vk['cert'],$vk['ca'],'','');$I=@$this->real_connect((!$Uc?$N["host"]:ini_get("mysqli.default_host")),(!$Uc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Uc||$V.$E!=""?$E:ini_get("mysqli.default_pw")),null,($xi!=""?intval($xi):ini_get("mysqli.default_port")),($xi!=""?null:$N["socket"]),($dm?($vk['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($I?'':$this->error);}function
set_charset($db){if($this->send_charset($db))return
true;$this->send_charset('utf8');return$this->query("SET NAMES $db");}private
function
send_charset($db){return
adminer()->executeQuery("SET NAMES $db",function()use($db){return
parent::set_charset($db);});}function
select_db($Zb){return
adminer()->executeQuery("USE ".idf_escape($Zb),function()use($Zb){return
parent::select_db($Zb);});}function
query($G,$rj=MYSQLI_STORE_RESULT){return
adminer()->executeQuery($G,function()use($G,$rj){return
parent::query($G,$rj);});}function
multi_query($G){return
adminer()->executeQuery($G,function()use($G){return
parent::multi_query($G);});}function
next_result(){return
self::more_results()&&parent::next_result();}function
quote($Q){return"'".$this->escape_string($Q)."'";}function
inTransaction(){return
false;}function
begin(){return(method_exists($this,'begin_transaction')?adminer()->executeQuery("BEGIN",function(){return$this->begin_transaction();}):!!$this->query("BEGIN"));}function
commit($Ld=0,$A=null){return
adminer()->executeQuery("COMMIT",function(){return
parent::commit();});}function
rollback($Ld=0,$A=null){return
adminer()->executeQuery("ROLLBACK",function(){return
parent::rollback();});}}}elseif(extension_loaded("mysql")&&!((ini_bool("sql.safe_mode")||ini_bool("mysql.allow_local_infile"))&&extension_loaded("pdo_mysql"))){class
Db
extends
SqlDb{private$link;function
attach(array$N,$V,$E){if(ini_bool("mysql.allow_local_infile"))return
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$xi="$N[port]$N[socket]";$A=$N["host"].($xi!=""?":$xi":"");$this->link=@mysql_connect(($A!=""?$A:ini_get("mysql.default_host")),($A.$V!=""?$V:ini_get("mysql.default_user")),($A.$V.$E!=""?$E:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($db){return$this->send_charset($db)||$this->send_charset('utf8');}private
function
send_charset($db){return
adminer()->executeQuery("SET NAMES $db",function()use($db){return
mysql_set_charset($db,$this->link);});}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Zb){return
adminer()->executeQuery("USE ".idf_escape($Zb),function()use($Zb){return
mysql_select_db($Zb,$this->link);});}function
query($G,$Kl=false){$H=adminer()->executeQuery($G,function()use($G,$Kl){return@($Kl?mysql_unbuffered_query($G,$this->link):mysql_query($G,$this->link));});$this->error="";if(!$H){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
false;}if($H===true){$this->affected_rows=mysql_affected_rows($this->link);$this->info=mysql_info($this->link);return
true;}return
new
Result($H);}}class
Result{var$num_rows;private$result;private$offset=0;function
__construct($H){$this->result=$H;$this->num_rows=mysql_num_rows($H);}function
fetch_assoc(){return
mysql_fetch_assoc($this->result);}function
fetch_row(){return
mysql_fetch_row($this->result);}function
fetch_field(){$I=mysql_fetch_field($this->result,$this->offset++);$I->orgtable=$I->table;$I->native_type=idx(["string"=>"varchar","real"=>"double"],$I->type,$I->type);return$I;}}}elseif(extension_loaded("pdo_mysql")){class
Db
extends
PdoDb{var$extension="PDO_MySQL";function
attach(array$N,$V,$E){$B=[\PDO::MYSQL_ATTR_LOCAL_INFILE=>false];if(isset($_GET["select"]))$B[1012]=false;$vk=adminer()->connectSsl();if($vk){if($vk['key'])$B[\PDO::MYSQL_ATTR_SSL_KEY]=$vk['key'];if($vk['cert'])$B[\PDO::MYSQL_ATTR_SSL_CERT]=$vk['cert'];if($vk['ca'])$B[\PDO::MYSQL_ATTR_SSL_CA]=$vk['ca'];if(isset($vk['verify']))$B[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$vk['verify'];}$Fe=$N["host"];$xi=$N["port"];$jk=$N["socket"];return$this->dsn("mysql:charset=utf8".($Fe!=""?";host=$Fe":'').($xi!=""?";port=$xi":($jk!=""?";unix_socket=$jk":"")),$V,$E,$B);}function
set_charset($db){return$this->query("SET NAMES $db");}function
select_db($Zb){return$this->query("USE ".idf_escape($Zb));}function
query($G,$Kl=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Kl);return
parent::query($G,$Kl);}}}class
Driver
extends
SqlDriver{static$extensions=["MySQLi","MySQL","PDO_MySQL"];static$jush="sql";static$serverSocket=true;var$unsigned=["unsigned","zerofill","unsigned zerofill"];var$functions=["char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper"];var$grouping=["avg","count","count distinct","group_concat","max","min","sum"];var$partitionBy=["HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST"];function
operators($Nk){return["=","<",">","<=",">=","!=","&","LIKE","LIKE %%","REGEXP","IN","BETWEEN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL"];}static
function
connect($N,$V,$E){$e=parent::connect($N,$V,$E);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($Dj=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$Dj;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=['Numbers'=>["tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21],'Date and time'=>["date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4],'Strings'=>["char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295],'Lists'=>["enum"=>65535,"set"=>64],'Binary'=>["bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295],'Geometry'=>["geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0],];$this->insertFunctions=["char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",];$this->editFunctions=[number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",];if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=["STORED","VIRTUAL"];}function
unconvertFunction(array$j){return(preg_match("~binary~",$j["type"])?"<code class='jush-sql'>UNHEX</code>":($j["type"]=="bit"?doc_link(['sql'=>'bit-value-literals.html'],"<code>b''</code>"):($j["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$j["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$O){return($O?parent::insert($R,$O):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$K,array$Ii){$d=array_keys(reset($K));$Ei="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$lm=[];foreach($d
as$v)$lm[$v]="$v = VALUES($v)";$Fk="\nON DUPLICATE KEY UPDATE ".implode(", ",$lm);$lm=[];$w=0;foreach($K
as$O){$Y="(".implode(", ",$O).")";if($lm&&(strlen($Ei)+$w+strlen($Y)+strlen($Fk)>1e6)){if(!queries($Ei.implode(",\n",$lm).$Fk))return
false;$lm=[];$w=0;}$lm[]=$Y;$w+=strlen($Y)+2;}return
queries($Ei.implode(",\n",$lm).$Fk);}function
slowQuery($G,$ml){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$ml FOR $G";elseif(preg_match('~^(SELECT\b)(.+)~is',$G,$_))return"$_[1] /*+ MAX_EXECUTION_TIME(".($ml*1000).") */ $_[2]";}}function
convertColumn($s,array$j){if(preg_match("~binary~",$j["type"]))return"HEX($s)";if($j["type"]=="bit")return"BIN($s + 0)";if($j["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($s)";if(preg_match("~geom|point|linestring|polygon~",$j["type"]))return(min_version(8)?"ST_":"")."AsWKT($s)";return"";}function
convertSearch($s,array$X,array$j){return($this->convertColumn($s,$j)?:(preg_match('~'.text_type().'~',$j["type"])&&!preg_match("~^utf8~",$j["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($s USING ".charset($this->conn).")":$s));}function
typeName(\stdClass$j){$A=parent::typeName($j);if($A!=""){$Jl=["TINY"=>"tinyint","SHORT"=>"smallint","LONG"=>"int","INT24"=>"mediumint","LONGLONG"=>"bigint","NEWDECIMAL"=>"decimal","VAR_STRING"=>"varchar","STRING"=>"char",];return
idx($Jl,$A,strtolower($A));}$Jl=["decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",];$I=idx($Jl,$j->type,"");return($j->charsetnr==63?str_replace(["text","varchar","char"],["blob","varbinary","binary"],$I):$I);}function
quoteBinary($Dj){return"X".q(bin2hex($Dj));}function
md5($c,array$j){if(is_blob($j)||preg_match('~'.text_type().'~',$j["type"]))return"MD5(".(is_blob($j)||preg_match("~^utf8~",$j["collation"])?$c:"CONVERT($c USING ".charset($this->conn).")").")";}function
warnings(){$H=$this->conn->query("SHOW WARNINGS");if($H&&$H->num_rows){ob_start();print_select_result($H);return
ob_get_clean();}}function
errorLine($G){return(preg_match('~ at line (\d+)$~',$this->conn->error,$_)?(int)$_[1]:0);}function
tableHelp($A,$sf=false){$ag=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($ag?"$A-table/":str_replace("_","-",$A)."-table.html"));if(DB=="sys")return($ag?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$A)).".html"));if(DB=="mysql")return($ag?"mysql$A-table/":"system-schema.html");}function
partitionsInfo($R){$Wd="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$H=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Wd ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$J=($H?$H->fetch_row():null);if(!$J)return[];$I=[];list($I["partition_by"],$I["partition"],$I["partitions"])=$J;$gi=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Wd AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$I["partition_names"]=array_keys($gi);$I["partition_values"]=array_values($gi);return$I;}function
checkConstraints($R){$I=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$I:array_map('stripslashes',$I));}function
hasCStyleEscapes(){static$Va;if($Va===null){$tk=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Va=(strpos($tk,'NO_BACKSLASH_ESCAPES')===false);}return$Va;}function
hasEstimatedRows(){return
true;}function
isSystem($g,$L=""){return
information_schema($g,$L)||in_array($g,["mysql","sys"]);}function
lineComment(){return"#|-- ";}function
engines(){$I=[];foreach(get_rows("SHOW ENGINES")as$J){if(preg_match("~YES|DEFAULT~",$J["Support"]))$I[]=$J["Engine"];}return$I;}function
indexAlgorithms(array$Nk){return(preg_match('~^(MEMORY|NDB)$~',$Nk["Engine"])?["HASH","BTREE"]:[]);}}function
idf_escape($s){return"`".str_replace("`","``",$s)."`";}function
table($s){return
idf_escape($s);}function
get_databases($Nd){$I=get_session("dbs");if($I===null){$G="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$wk=microtime(true);$I=($Nd?slow_query($G):get_vals($G));if(microtime(true)-$wk>0.1){restart_session();set_session("dbs",$I);stop_session();}}return$I;}function
limit($G,$Z,$x,$oh=0,$Tj=" "){return" $G$Z".($x?$Tj."LIMIT $x".($oh?" OFFSET $oh":""):"");}function
limit1($R,$G,$Z,$Tj="\n"){return
limit($G,$Z,1,0,$Tj);}function
db_collation($g,array$sb){static$I;if($I===null)$I=get_key_vals("SELECT SCHEMA_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA");return$I[$g];}function
logged_user(){return
get_val("SELECT CURRENT_USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$f){$I=[];foreach($f
as$g)$I[$g]=count(get_vals("SHOW TABLES IN ".idf_escape($g)));return$I;}function
table_status($A="",$yd=false){$I=[];$G="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($A!=""?"AND TABLE_NAME = ".q($A):"ORDER BY Name");$L=[];foreach(($yd?[]:get_rows($G))as$J)$L[$J["Name"]]=$J;$Hi=null;foreach(get_rows($yd?$G:"SHOW TABLE STATUS".($A!=""?" LIKE ".q(addcslashes($A,"%_\\")):""))as$J){$Ph=idx($L,$J["Name"]);if($Ph){if($J["Comment"]!==$Ph["Comment"]&&$J["Comment"]!==$Hi)$J["Error"]=$J["Comment"];$Hi=$J["Comment"];$J["Comment"]=$Ph["Comment"];$J["Engine"]=$Ph["Engine"];}if($J["Engine"]=="InnoDB")$J["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$J["Comment"]);if(!isset($J["Engine"]))$J["Comment"]="";if($A!="")$J["Name"]=$A;$I[$J["Name"]]=$J;}return$I;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Yd){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Yd,$_);return[$_[1],$_[2],ltrim($_[3].$_[4])];}function
fields($R){$ag=(connection()->flavor=='maria');$I=[];foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$J){$j=$J["COLUMN_NAME"];$U=$J["COLUMN_TYPE"];$de=$J["GENERATION_EXPRESSION"];$vd=$J["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$vd,$ce);list($Il,$w,$Rl)=parse_type($U);$h=$J["COLUMN_DEFAULT"];$hc=false;if($h!=""){$Wi=$ag;if($ag)$hc=!preg_match("~^('(?:[^'\\\\]|\\\\.|'')*'|NULL|[-+]?[\\d.]+)$~s",$h);elseif(preg_match('~text|json~',$Il)||preg_match('~\bDEFAULT_GENERATED\b~',$vd)){$h=stripslashes($h);$Wi=preg_match("~^(_\\w+)?('(?:[^'\\\\]|\\\\.|'')*')$~s",$h,$_);if($Wi)$h=$_[2];else$hc=true;}elseif(preg_match("~^[bx]'[\\da-f]*'$~i",$h))$hc=true;if($Wi)$h=($h=="NULL"?null:(preg_match("~^'.*'$~s",$h)?stripslashes(idf_unescape($h)):$h));if(preg_match('~binary~',$Il)&&preg_match("~^(?:0x(\\w*)|x'(\\w*)')$~",$h,$_)){$h=pack("H*",$_[1].idx($_,2));$hc=false;}}$I[$j]=["field"=>$j,"full_type"=>$U,"type"=>$Il,"length"=>$w,"unsigned"=>$Rl,"default"=>($ce?($ag?$de:stripslashes($de)):$h),"default_sql"=>(!$ce&&$hc),"null"=>($J["IS_NULLABLE"]=="YES"),"auto_increment"=>($vd=="auto_increment"),"on_update"=>(preg_match('~\bon update current_timestamp\b~i',$vd)?"CURRENT_TIMESTAMP":""),"collation"=>$J["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$J[PRIVILEGES],where,order")),"comment"=>$J["COLUMN_COMMENT"],"primary"=>($J["COLUMN_KEY"]=="PRI"),"generated"=>($ce[1]=="PERSISTENT"?"STORED":$ce[1]),];}return$I;}function
indexes($R,$Bb=null){$I=[];foreach(get_rows("SHOW INDEX FROM ".table($R),$Bb)as$J){$A=$J["Key_name"];$I[$A]["type"]=($A=="PRIMARY"?"PRIMARY":($J["Index_type"]=="FULLTEXT"?"FULLTEXT":($J["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$J["Index_type"])?$J["Index_type"]:"INDEX"):"UNIQUE")));$I[$A]["columns"][]=$J["Column_name"];$I[$A]["lengths"][]=($J["Index_type"]=="SPATIAL"?null:$J["Sub_part"]);$I[$A]["descs"][]=null;$I[$A]["algorithm"]=$J["Index_type"];}return$I;}function
foreign_keys($R){static$oi='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$I=[];$Nb=get_val("SHOW CREATE TABLE ".table($R),1);if($Nb){preg_match_all("~CONSTRAINT ($oi) FOREIGN KEY ?\\(((?:$oi,? ?)+)\\) REFERENCES ($oi)(?:\\.($oi))? \\(((?:$oi,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Nb,$cg,PREG_SET_ORDER);foreach($cg
as$_){preg_match_all("~$oi~",$_[2],$nk);preg_match_all("~$oi~",$_[5],$cl);$I[idf_unescape($_[1])]=["db"=>idf_unescape($_[4]!=""?$_[3]:$_[4]),"table"=>idf_unescape($_[4]!=""?$_[4]:$_[3]),"source"=>array_map('Adminer\idf_unescape',$nk[0]),"target"=>array_map('Adminer\idf_unescape',$cl[0]),"on_delete"=>($_[6]?:"RESTRICT"),"on_update"=>($_[7]?:"RESTRICT"),];}}return$I;}function
view($A){return["select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($A),1))];}function
collations(){$I=[];foreach(get_rows("SHOW COLLATION")as$J)$I[$J["Charset"]][]=$J["Collation"];ksort($I);foreach($I
as$v=>$X)sort($I[$v]);return$I;}function
information_schema($g,$L=""){return($g=="information_schema")||(min_version(5.5)&&$g=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($g,$rb){return
queries("CREATE DATABASE ".idf_escape($g).($rb?" COLLATE ".q($rb):""));}function
drop_databases(array$f){$I=apply_queries("DROP DATABASE",$f,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$I;}function
rename_database($A,$rb){$I=false;if(create_database($A,$rb)){$T=[];$qm=[];foreach(tables_list()as$R=>$U){if($U=='VIEW')$qm[]=$R;else$T[]=$R;}$I=(!$T&&!$qm)||move_tables($T,$qm,$A);drop_databases($I?[DB]:[]);}return$I;}function
auto_increment(){$Ea=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$t){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$t["columns"],true)){$Ea="";break;}if($t["type"]=="PRIMARY")$Ea=" UNIQUE";}}return" AUTO_INCREMENT$Ea";}function
alter_table($R,$A,array$k,array$Pd,$wb,$Xc,$rb,$Da,$fi){$ta=[];foreach($k
as$j){if($j[1]){$h=$j[1][3];if(preg_match('~ GENERATED~',$h)){$j[1][3]=(connection()->flavor=='maria'?"":$j[1][2]);$j[1][2]=$h;}$ta[]=($R!=""?($j[0]!=""?"CHANGE ".idf_escape($j[0]):"ADD"):" ")." ".implode($j[1]).($R!=""?$j[2]:"");}else$ta[]="DROP ".idf_escape($j[0]);}$ta=array_merge($ta,$Pd);$P=($wb!==null?" COMMENT=".q($wb):"").($Xc?" ENGINE=".q($Xc):"").($rb?" COLLATE ".q($rb):"").($Da!=""?" AUTO_INCREMENT=$Da":"");if($fi){$gi=[];if($fi["partition_by"]=='RANGE'||$fi["partition_by"]=='LIST'){foreach($fi["partition_names"]as$v=>$X){$Y=$fi["partition_values"][$v];$gi[]="\n  PARTITION ".idf_escape($X)." VALUES ".($fi["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$P
.="\nPARTITION BY $fi[partition_by]($fi[partition])";if($gi)$P
.=" (".implode(",",$gi)."\n)";elseif($fi["partitions"])$P
.=" PARTITIONS ".(+$fi["partitions"]);}elseif($fi===null)$P
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($A)." (\n".implode(",\n",$ta)."\n)$P");if($R!=$A)$ta[]="RENAME TO ".table($A);if($P)$ta[]=ltrim($P);return($ta?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$ta)):true);}function
alter_indexes($R,$ta){$bb=[];foreach($ta
as$X)$bb[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$bb));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$qm){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$qm)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$qm,$cl){$kj=[];foreach($T
as$R)$kj[]=table($R)." TO ".idf_escape($cl).".".table($R);if(!$kj||queries("RENAME TABLE ".implode(", ",$kj))){$lc=[];foreach($qm
as$R)$lc[table($R)]=view($R);connection()->select_db($cl);$g=idf_escape(DB);foreach($lc
as$A=>$pm){if(!queries("CREATE VIEW $A AS ".str_replace(" $g."," ",$pm["select"]))||!queries("DROP VIEW $g.$A"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$qm,$cl){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$A=($cl==DB?table("copy_$R"):idf_escape($cl).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $A"))||!queries("CREATE TABLE $A LIKE ".table($R))||!queries("INSERT INTO $A SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$J){$Al=$J["Trigger"];list($hd,$jh)=trigger_event($J);if(!queries("CREATE TRIGGER ".($cl==DB?idf_escape("copy_$Al"):idf_escape($cl).".".idf_escape($Al))." $J[Timing] $hd".($jh!=""?" $jh":"")." ON $A FOR EACH ROW\n$J[Statement];"))return
false;}}foreach($qm
as$R){$A=($cl==DB?table("copy_$R"):idf_escape($cl).".".table($R));$pm=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $A"))||!queries("CREATE VIEW $A AS $pm[select]"))return
false;}return
true;}function
trigger_event(array$J){$jd=explode(",",$J["Event"]);$I=[];foreach(["DELETE","INSERT","UPDATE"]as$hd){if(in_array($hd,$jd))$I[]=$hd;}$I=implode(" OR ",$I);if(in_array("UPDATE",$jd)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($J["Trigger"]),2),$_)&&preg_match('~\bOF\s+(.+)~is',$_[1],$jh))return["$I OF",$jh[1]];return[$I,""];}function
trigger($A,$R){if($A=="")return[];$K=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($A));$I=reset($K);if($I)list($I["Event"],$I["Of"])=trigger_event($I);return($I?:[]);}function
triggers($R){$I=[];foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$J){list($hd)=trigger_event($J);$I[$J["Trigger"]]=[$J["Timing"],$hd];}return$I;}function
trigger_options(){return["Timing"=>["BEFORE","AFTER"],"Event"=>(min_version('','12.0.1')?["INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",]:["INSERT","UPDATE","DELETE"]),"Type"=>["FOR EACH ROW"],];}function
routine($A,$U){$K=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($A)."
ORDER BY ORDINAL_POSITION");$k=[];foreach($K
as$J){$Yd=$J["DTD_IDENTIFIER"];list($Il,$w,$Rl)=parse_type($Yd);$k[]=["field"=>$J["PARAMETER_NAME"],"type"=>$Il,"length"=>$w,"unsigned"=>$Rl,"null"=>true,"full_type"=>$Yd,"inout"=>($U=="FUNCTION"?"":$J["PARAMETER_MODE"]),"collation"=>$J["COLLATION_NAME"],];}$I=connection()->query("SELECT
	ROUTINE_COMMENT comment,
	ROUTINE_DEFINITION definition,
	LOWER(EXTERNAL_LANGUAGE) language,
	IF(DEFINER = CURRENT_USER(), '', DEFINER) definer,
	IF(IS_DETERMINISTIC = 'YES', 'DETERMINISTIC', 'NOT DETERMINISTIC') is_deterministic,
	SQL_DATA_ACCESS data_access,
	CONCAT('SQL SECURITY ', SECURITY_TYPE) security
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND ROUTINE_NAME = ".q($A))->fetch_assoc();if(!$I)return[];$I['options']=["DEFINER"=>$I['definer'],"DETERMINISTIC"=>$I['is_deterministic'],"SQL_DATA_ACCESS"=>$I['data_access'],"SQL_SECURITY"=>$I['security'],"COMMENT"=>$I['comment'],];if($k&&$k[0]['field']=='')$I['returns']=array_shift($k);$I['fields']=$k;return$I;}function
routines(){return
get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");}function
routine_languages(){return(min_version(9,99)?["sql"=>"sql","javascript"=>"js"]:[]);}function
routine_options($wj){return["DEFINER"=>[],"DETERMINISTIC"=>["NOT DETERMINISTIC","DETERMINISTIC"],"SQL_DATA_ACCESS"=>["CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"],"SQL_SECURITY"=>["SQL SECURITY DEFINER","SQL SECURITY INVOKER"],"COMMENT"=>[],];}function
routine_id($A,array$J){return
idf_escape($A);}function
last_id($H){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$G){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$G);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Da,$Dk){$I=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Da)$I=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$I);return$I;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Zb,$Dk=""){$A=idf_escape($Zb);$I="";if(preg_match('~CREATE~',$Dk)&&($Mb=get_val("SHOW CREATE DATABASE $A",1))){set_utf8mb4($Mb);if($Dk=="DROP+CREATE")$I="DROP DATABASE IF EXISTS $A;\n";$I
.="$Mb;\n";}return$I."USE $A";}function
trigger_sql($R){$I="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")),null,"-- ")as$J){list($J["Event"],$J["Of"])=trigger_event($J);$I
.="\n".create_trigger(" ON ".table($J["Table"]),$J+["Type"=>"FOR EACH ROW"]).";\n";}return$I;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$j){return
driver()->convertColumn(idf_escape($j["field"]),$j);}function
unconvert_field(array$j,$I){if(preg_match("~binary~",$j["type"]))$I="UNHEX($I)";if($j["type"]=="bit")$I="CONVERT(b$I, UNSIGNED)";if($j["type"]=="vector")$I=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($I)";if(preg_match("~geom|point|linestring|polygon~",$j["type"])){$Ei=(min_version(8)?"ST_":"");$I=$Ei."GeomFromText($I, $Ei"."SRID($j[field]))";}return$I;}function
support($zd){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$zd);}function
kill_process($r){return
queries("KILL ".number($r));}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($ud=false){return[];}function
type_values($r){return"";}function
type_definition($r){return["kind"=>"","definition"=>""];}function
schemas(){return[];}function
get_schema(){return"";}function
set_schema($L,$Bb=null){return
true;}function
schema_comment($L){return"";}function
set_schema_comment($L,$wb){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));if(isset($_GET["manifest"])){header("Content-Type: application/manifest+json; charset=utf-8");header("Cache-Control: no-cache");echo
json_encode(adminer()->manifest(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}function
page_header($ol,$i="",$Ta=[],$pl="",$eh=false,$Bc=""){if($eh){header("HTTP/1.1 404 Not Found");$i=($i?:'Not found.');}page_headers();if(is_ajax()&&$i){page_messages($i);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$ql=$ol.($pl!=""?": $pl":"");$rl=strip_tags($ql.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$rl,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.3.0+97df70eb"),'">
';$Rb=adminer()->css();if(is_int(key($Rb)))$Rb=array_fill_keys($Rb,'light');$re=in_array('light',$Rb)||in_array('',$Rb);$pe=in_array('dark',$Rb)||in_array('',$Rb);$Vb=($re?($pe?null:false):($pe?:null));$rg=" media='(prefers-color-scheme: dark)'";if($Vb!==false)echo"<link rel='stylesheet'".($Vb?"":$rg)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.3.0+97df70eb")."'>\n";echo"<meta name='color-scheme' content='".($Vb===null?"light dark":($Vb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.3.0+97df70eb");if(adminer()->head($Vb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.3.0+97df70eb")."'>\n";if(adminer()->manifest())echo"<link rel='manifest' href='".h(preg_replace('~\?.*~','',ME)."?manifest=")."' crossorigin='use-credentials'>\n";foreach($Rb
as$Yl=>$Gg){$b=($Gg=='dark'&&!$Vb?$rg:($Gg=='light'&&$pe?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($Yl)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Ta!==null){$wf=(class_exists('Adminer\Db')&&connection());$y=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb">'.($wf?jump_input():'').'<a href="'.h($y?:".").'">'.get_driver(DRIVER).'</a> » ';$y=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$N=adminer()->serverName(SERVER);$N=($N!=""?$N:'Server');if($Ta===false)echo"$N\n";else{echo"<a href='".h($y.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$N</a> » ";$Lj="";if(is_string($Ta)){$Lj=$Ta;$Ta=[];}if($_GET["ns"]!=""||(DB!=""&&is_array($Ta))){$bc="$y&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"");echo'<a href="'.h($bc.($_GET["ns"]==""?$Lj:"")).'">'.h(DB).'</a> » ';}if(is_array($Ta)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1).$Lj).'">'.h($_GET["ns"]).'</a> » ';foreach($Ta
as$v=>$X){$nc=(is_array($X)?$X[1]:h($X));if($nc!="")echo"<a href='".h(ME."$v=").url_escape(is_array($X)?$X[0]:$X)."'>$nc</a> » ";}}echo"$ol\n";}if($wf){echo"<form id='jump-form'>\n";hidden_fields_get();echo(DB!=""?input_hidden("db",DB):""),(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""),"</form>\n",script("jumpSetup();");}}echo"<h2>$ql$Bc</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($i);adminer()->serviceWorker();$f=&get_session("dbs");if(DB!=""&&$f&&!in_array(DB,$f,true))$f=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();if($eh){page_footer($eh===true?"":$eh);exit;}}function
service_worker(){$ij=has_passwords();$ob=($ij?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=6.3.0+97df70eb")."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$ob\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$ak){foreach($ak
as$gm){foreach($gm
as$E){if($E!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Qb){$ve=[];foreach($Qb
as$v=>$X)$ve[]="$v $X";header("Content-Security-Policy: ".implode("; ",$ve));}adminer()->headers();}function
csp(){return[["script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",],];}function
design_checksums(){$em=[];foreach(array_keys(adminer()->css())as$Yl)$em[preg_replace('~\?.*~','',$Yl)]=true;$I=[];foreach(["adminer.css","adminer-dark.css"]as$l){if($em[$l]&&file_exists($l)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($l),$_);$I[$l]=[(string)$_[1],Plugins::checksum($l)];}}return$I;}function
official_design_checksums(){return['adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'c9d1d11c','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'d9169f12','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'a8babfc5','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'a38b3b6a','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',];}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$dh;if(!$dh)$dh=base64_encode(rand_string());return$dh;}function
page_messages($i){$Xl=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$yg=idx($_SESSION["messages"],$Xl);if($yg){echo"<div class='message'>".implode("</div>\n<div class='message'>",$yg)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$Xl]);}if($i)echo"<div class='error'>$i</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($Fg=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($Fg);echo"</div>\n";if($Fg!="auth")echo'<form method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',adminer()->donation()."\n",input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($Og){while($Og>=2147483648)$Og-=4294967296;while($Og<=-2147483649)$Og+=4294967296;return(int)$Og;}function
long2str(array$W,$sm){$Dj='';foreach($W
as$X)$Dj
.=pack('V',$X);if($sm)return
substr($Dj,0,end($W));return$Dj;}function
str2long($Dj,$sm){$W=array_values(unpack('V*',str_pad($Dj,4*ceil(strlen($Dj)/4),"\0")));if($sm)$W[]=strlen($Dj);return$W;}function
xxtea_mx($Bm,$Am,$Gk,$yf){return
int32((($Bm>>5&0x7FFFFFF)^$Am<<2)+(($Am>>3&0x1FFFFFFF)^$Bm<<4))^int32(($Gk^$Am)+($yf^$Bm));}function
encrypt_string($Ak,$v){if($Ak=="")return"";$v=array_values(unpack("V*",pack("H*",md5($v))));$W=str2long($Ak,true);$Og=count($W)-1;$Bm=$W[$Og];$Am=$W[0];$Qi=floor(6+52/($Og+1));$Gk=0;while($Qi-->0){$Gk=int32($Gk+0x9E3779B9);$Kc=$Gk>>2&3;for($Th=0;$Th<$Og;$Th++){$Am=$W[$Th+1];$Ng=xxtea_mx($Bm,$Am,$Gk,$v[$Th&3^$Kc]);$Bm=int32($W[$Th]+$Ng);$W[$Th]=$Bm;}$Am=$W[0];$Ng=xxtea_mx($Bm,$Am,$Gk,$v[$Th&3^$Kc]);$Bm=int32($W[$Og]+$Ng);$W[$Og]=$Bm;}return
long2str($W,false);}function
decrypt_string($Ak,$v){if($Ak=="")return"";if(!$v)return
false;$v=array_values(unpack("V*",pack("H*",md5($v))));$W=str2long($Ak,false);$Og=count($W)-1;$Bm=$W[$Og];$Am=$W[0];$Qi=floor(6+52/($Og+1));$Gk=int32($Qi*0x9E3779B9);while($Gk){$Kc=$Gk>>2&3;for($Th=$Og;$Th>0;$Th--){$Bm=$W[$Th-1];$Ng=xxtea_mx($Bm,$Am,$Gk,$v[$Th&3^$Kc]);$Am=int32($W[$Th]-$Ng);$W[$Th]=$Am;}$Bm=$W[$Og];$Ng=xxtea_mx($Bm,$Am,$Gk,$v[$Th&3^$Kc]);$Am=int32($W[0]-$Ng);$W[0]=$Am;$Gk=int32($Gk-0x9E3779B9);}return
long2str($W,true);}$ri=[];if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($v)=explode(":",$X);$ri[$v]=$X;}}function
add_invalid_login(){$La=get_temp_dir()."/adminer-invalid";foreach(glob("$La*")?:[$La]as$l){$n=file_open_lock($l);if($n)break;}if(!$n)$n=file_open_lock("$La-".rand_string());if(!$n)return;$kf=json_decode(stream_get_contents($n),true);$ll=time();if($kf){foreach($kf
as$lf=>$X){if($X[0]<$ll)unset($kf[$lf]);}}$if=&$kf[adminer()->bruteForceKey()];if(!$if)$if=[$ll+30*60,0];$if[1]++;file_write_unlock($n,json_encode($kf));}function
check_invalid_login(array&$ri){$kf=[];foreach(glob(get_temp_dir()."/adminer-invalid*")as$l){$n=file_open_lock($l);if($n){$kf=json_decode(stream_get_contents($n),true);file_unlock($n);break;}}$v=adminer()->bruteForceKey();$if=idx($kf,$v,[]);$ch=($if[1]>29?$if[0]-time():0);if($ch>0){$i=lang_format(['Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'],ceil($ch/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$v==$_SERVER["REMOTE_ADDR"])$i
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($i,$ri,false);}}function
password_required(){static$I;if($I===null){$I=(bool)get_session("password_required");if(!$I){$Pb=adminer()->credentials();$I=!is_object(Driver::connect($Pb[0],$Pb[1],""));if($I)set_session("password_required",true);}}return$I;}function
require_password_link($E){$Kg="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $Kg";$ui=($E!==null?$E:base64_encode(substr(hex2bin(rand_string()),0,12)));$ue=password_hash($ui,PASSWORD_DEFAULT);$l="adminer-plugins.php";$od=file_exists("adminer-plugins.php");if($od)$gf=($E!==null?sprintf('Add this line to %s to require the entered password:',"<b>$l</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$l</b>","<b>$ui</b>"));else{$l="<button name='password_less' value='".h($ue)."' class='link'>$l</button>";$gf=($E!==null?sprintf('Save %s next to Adminer to require the entered password:',$l):sprintf('Save %s next to Adminer to require the password %s:',$l,"<b>$ui</b>"));}$Sf="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($ue)."'</span>),";$I="<p>$gf
<pre><code class='jush'>".($od?$Sf:"&lt;?php\n<a>return</a> <a>array</a>(\n$Sf\n);")."</code></pre>
<p>$Kg
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($od?$I:"<form method='post'>\n".$I.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$Ca=$_POST["auth"];if($Ca&&(!adminer()->verifyLoginToken()||verify_token())){session_regenerate_id();$nm=$Ca["driver"];$N=$Ca["server"];$V=$Ca["username"];$E=(string)$Ca["password"];$g=$Ca["db"];set_password($nm,$N,$V,$E);$_SESSION["db"][$nm][$N][$V][$g]=true;if($Ca["permanent"]){$v=implode("-",array_map('base64_encode',[$nm,$N,$V,$g]));$Ki=adminer()->permanentLogin(true);$ri[$v]="$v:".base64_encode($Ki?encrypt_string($E,$Ki):"");cookie("adminer_permanent",implode(" ",$ri));}if(!array_diff(array_keys($_POST),["auth","token"])||$nm!=DRIVER||$N!=SERVER||$V!==$_GET["username"]||$g!=DB)redirect(auth_url($nm,$N,$V,$g));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){Driver::disconnect();foreach(["pwds","db","dbs","queries"]as$v)set_session($v,null);unset_permanent($ri);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.sprintf('Thanks for using Adminer. Consider <a%s>donating</a>.'," href='https://www.adminer.org/donation/'".target_blank()));}elseif($ri&&!$_SESSION["pwds"]){session_regenerate_id();$Ki=adminer()->permanentLogin();foreach($ri
as$v=>$X){list(,$mb)=explode(":",$X);list($nm,$N,$V,$g)=array_map('base64_decode',explode("-",$v));set_password($nm,$N,$V,decrypt_string(base64_decode($mb),$Ki));$_SESSION["db"][$nm][$N][$V][$g]=true;}}function
unset_permanent(array&$ri){foreach($ri
as$v=>$X){list($nm,$N,$V,$g)=array_map('base64_decode',explode("-",$v));if($nm==DRIVER&&$N==SERVER&&$V==$_GET["username"]&&$g==DB)unset($ri[$v]);}cookie("adminer_permanent",implode(" ",$ri));}function
auth_error($i,array&$ri,$jf=true){$bk=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$bk]||$_GET[$bk])&&!$_SESSION["token"])$i='Session expired. Please log in again.';elseif($jf&&($E=get_password())!==null){restart_session();add_invalid_login();if($E===false)$i
.=($i?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($ri);}}if(!$_COOKIE[$bk]&&$_GET[$bk]&&ini_bool("session.use_only_cookies"))$i='Session support must be enabled.';$Xh=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$Xh["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$i,null);echo"<form method='post'>\n","<div>";if(hidden_fields($_POST,["auth","token"]))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($ri);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($ri);$Pb=adminer()->credentials();$e=Driver::connect($Pb[0],$Pb[1],$Pb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(["vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)]);}}$Yf=null;if(!is_object($e)||($Yf=adminer()->login($_GET["username"],get_password()))!==true){$i=(is_string($e)?nl_br(h($e)):(is_string($Yf)?$Yf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($i,$ri);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);$i='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$i='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$i=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$i
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($H,$Bb=null,array$Jh=[],&$x=0,&$Lc=false){$Uf=[];$u=[];$d=[];$T=[];$Ii=[];$Pc=[];$Jl=[];$I=[];$Hg=$Lc;$Lc=false;for($q=0;(!$x||$q<$x)&&is_array($J=$H->fetch_row());$q++){if(!$q){echo"<div class='scrollable'>\n","<table class='nowrap odds'".($Hg?on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown'):"").">\n","<thead><tr>";for($uf=0;$uf<count($J);$uf++){$j=$H->fetch_field();$A=$j->name;$R=(isset($j->table)?$j->table:"");$Ih=(isset($j->orgtable)?$j->orgtable:"");$Hh=(isset($j->orgname)?$j->orgname:$A);$Il=driver()->typeName($j);if($Jh&&JUSH=="sql")$Uf[$uf]=($A=="table"?"table=":($A=="possible_keys"?"indexes=":null));elseif($Ih!=""){$pa=($R!=""?$R:$Ih);if($R!="")$I[$R]=$Ih;if(!isset($u[$pa])){if(!isset($Ii[$Ih])){$Ii[$Ih]=[];foreach(indexes($Ih,$Bb)as$t){if($t["type"]=="PRIMARY"){$Ii[$Ih]=array_flip($t["columns"]);break;}}}$T[$pa]=$Ih;$u[$pa]=$Ii[$Ih];$d[$pa]=$Ii[$Ih];}if(isset($d[$pa][$Hh])){unset($d[$pa][$Hh]);$u[$pa][$Hh]=$uf;$Uf[$uf]=$pa;}elseif($Hg&&isset($j->orgname)&&$j->db==DB&&!is_blob(["type"=>$Il]))$Pc[$uf]=[$pa,$Hh,preg_match('~text|json|lob~',$Il)];}$Jl[$uf]=$Il;echo"<th title='".h(trim(($Ih!=""?"$Ih.$Hh":($j->name!=$Hh?$Hh:""))." ".$Il))."'>".h($A).($Jh?doc_link(['sql'=>"explain-output.html#explain_".strtolower($A),'mariadb'=>"explain/#the-columns-in-explain-select",]):"");}foreach($Pc
as$uf=>$Za){if($d[$Za[0]])unset($Pc[$uf]);}echo"<tbody>\n";}$Le=[];foreach($u
as$pa=>$t){if($t&&!$d[$pa]){$s="";foreach($t
as$pb=>$uf){if($J[$uf]===null){$s=null;break;}$s
.="&where[".url_escape(bracket_escape($pb))."]=".url_escape($J[$uf]);}$Le[$pa]=$s;}}echo"<tr>";foreach($J
as$v=>$X){$y="";if(isset($Uf[$v])){if($Jh&&JUSH=="sql"){$R=$J[array_search("table=",$Uf)];$y=ME.$Uf[$v].url_escape($Jh[$R]!=""?$Jh[$R]:$R);}elseif(idx($Le,$Uf[$v])!==null)$y=ME."edit=".url_escape($T[$Uf[$v]]).$Le[$Uf[$v]];}$He=select_value($X,$y,['type'=>(preg_match('~binary~',$Jl[$v])?'blob':$Jl[$v])],null);$b="";$Za=idx($Pc,$v);if($Za&&idx($Le,$Za[0])!==null&&is_utf8($X)){$Lc=true;$b=" data-name='".h("val[".bracket_escape($T[$Za[0]])."][".bracket_escape(substr($Le[$Za[0]],1))."][".bracket_escape($Za[1])."]")."' data-text='".($Za[2]?1:0)."'".(html_entity_decode(strip_tags($He),ENT_QUOTES,"UTF-8")!==$X?" data-value='".h($X)."'":"");}echo"<td".(preg_match(number_type(),$Jl[$v])?" class='number'":"")."$b>$He";}}$x=$q;echo($q?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$I;}function
textarea($A,$Y,$K=10,$tb=80,$xf=JUSH,$dd=0){echo"<textarea name='".h($A)."' rows='$K' cols='$tb' class='sqlarea jush-".h($xf)."' spellcheck='false' wrap='off'".($dd?" data-error-line='$dd'":"").">\n";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$B,$Y="",$si=""){if($B&&$Y!=""&&!isset($B[$Y]))$B=[$Y=>$Y]+$B;$bl=($B?"select":"input");return"<$bl$b".($B?"><option value=''>$si".optionlist($B,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$si'>");}function
json_row($v,$X=null,$gd=true){static$Kd=true;if($Kd)echo"{";if($v!=""){echo($Kd?"":",")."\n\t\"".addcslashes($v,"\r\n\t\"\\/").'": '.($X!==null?($gd?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$Kd=false;}else{echo"\n}\n";$Kd=true;}}function
flat_collations(){$sb=collations();return(is_array(reset($sb))?call_user_func_array('array_merge',array_values($sb)):$sb);}function
edit_type($v,array$j,array$sb,array$Rd=[],array$wd=[]){$U=(string)$j["type"];echo"<td><select name='".h($v)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($Rd[$U])&&!in_array($U,$wd))$wd[]=$U;$Bk=driver()->structuredTypes();if($Rd)$Bk['Foreign keys']=$Rd;echo
optionlist(array_merge($wd,$Bk),$U),"</select><td>","<input name='".h($v)."[length]' value='".h($j["length"])."' size='3'".(!$j["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($sb?"<input list='collations' name='".h($v)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($j["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($v)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$j["unsigned"]).'</select>':''),(isset($j['on_update'])?"<select name='".h($v)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist([""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"],(preg_match('~^CURRENT_TIMESTAMP~i',$j["on_update"])?"CURRENT_TIMESTAMP":$j["on_update"])).'</select>':''),($Rd?"<select name='".h($v)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$j["on_delete"])."</select> ":" ");}function
option_types($U,$Jl){return" data-types='".h($Jl)."'".(preg_match("~$Jl~",$U)?"":" class='hidden'");}function
process_length($w){if(JUSH=="mssql"&&preg_match('~^\s*\(?\s*max\s*\)?\s*$~i',$w))return"(max)";if(JUSH=="oracle"&&preg_match('~^\s*\(?\s*(\d+)\s+(CHAR|BYTE)\s*\)?\s*$~i',$w,$_))return"($_[1] ".strtoupper($_[2]).")";if(JUSH=="oracle"&&preg_match('~^\s*\(?\s*\*\s*,\s*(-?\d+)\s*\)?\s*$~',$w,$_))return"(*,$_[1])";$ad=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$ad(?:\\s*,\\s*$ad)*+\\s*\\)?\\s*\$~",$w)&&preg_match_all("~$ad~",$w,$cg)?"(".implode(",",$cg[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$w)));}function
process_in($X){$X=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$X);$Wi="'(?:[^']|'')*+'";$tf=(preg_match("~^\\s*$Wi(?:\\s*,\\s*$Wi)*+\\s*\$~",$X)&&preg_match_all("~$Wi~",$X,$cg)?array_map('Adminer\idf_unescape',$cg[0]):array_map('trim',explode(",",$X)));return"(".implode(", ",array_map('Adminer\q',$tf)).")";}function
process_between($X){$Wi="('(?:[^']|'')*+')";$Yi=(preg_match("~^\\s*$Wi\\s+AND\\s+$Wi\\s*\$~i",$X,$_)?[idf_unescape($_[1]),idf_unescape($_[2])]:preg_split('~\s+AND\s+~i',trim($X)));return(count($Yi)==2?q($Yi[0])." AND ".q($Yi[1]):"NULL AND NULL");}function
process_type(array$j,$qb="COLLATE"){return" ".(is_user_type($j["type"])?idf_escape($j["type"]):$j["type"]).process_length($j["length"]).(preg_match(number_type(),$j["type"])&&in_array($j["unsigned"],driver()->unsigned)?" $j[unsigned]":"").(preg_match('~'.text_type().'~',$j["type"])&&$j["collation"]?" $qb ".(JUSH=="mssql"&&preg_match('~^\w+$~',$j["collation"])?$j["collation"]:q($j["collation"])):"");}function
process_field(array$j,array$Gl){return[idf_escape(trim($j["field"])),process_type($Gl),($j["null"]?" NULL":" NOT NULL"),default_value($j),(preg_match('~timestamp|datetime~',$j["type"])&&$j["on_update"]?" ON UPDATE $j[on_update]".process_length($j["length"]):""),(support("comment")&&$j["comment"]!=""?" COMMENT ".q($j["comment"]):""),($j["auto_increment"]?auto_increment():null),];}function
default_value(array$j){if($j["default"]===null)return"";$h=str_replace("\r","",$j["default"]);$ce=$j["generated"];$Q=!preg_match('~]$~',$j["length"])&&(preg_match('~char|binary|text|clob|json|enum|set|String~',$j["type"])||driver()->enumLength($j));$Yh=(JUSH=="sqlite"||(JUSH=="sql"&&$j["default_sql"]&&!preg_match("~^(current_timestamp(\\(\\d*\\))?|[bx]'[\\da-f]*')$~i",$h)));return(in_array($ce,driver()->generated)?(JUSH=="mssql"?" AS ($h)".($ce=="VIRTUAL"?"":" $ce"):" GENERATED ALWAYS AS ($h) $ce"):(preg_match('~^GENERATED ~i',$h)?" $h":" DEFAULT ".(!$j["default_sql"]&&($Q||preg_match('~^(?![a-z])~i',$h))?(JUSH=="sql"&&preg_match('~text|json~',$j["type"])?"(".q($h).")":q($h)):($Yh?"($h)":$h))));}function
edit_fields(array$k,array$sb,$U="TABLE",array$Rd=[]){$k=array_values($k);$gc=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$xb=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<th id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<th id='label-length'>".'Length',"<th>".'Options';if($U=="TABLE")echo"<th id='label-null'>NULL\n","<th><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>A_I</abbr>",doc_link(['sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",]),"<th id='label-default'$gc>".'Default value',(support("comment")?"<th id='label-comment'$xb>".'Comment':"");$Hf=!support("move_col");echo"<td>".icon("plus","add[".($Hf?count($k):0)."]","+",'Add next',($Hf?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($k
as$q=>$j){$q++;$Kh=$j[($_POST?"orig":"field")];$uc=(isset($_POST["add"][$q-1])||(isset($j["field"])&&!idx($_POST["drop_col"],$q)))&&(support("drop_col")||$Kh=="");echo"<tr".($uc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$q][inout]",explode("|",driver()->inout),$j["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($uc)echo"<input name='fields[$q][field]' value='".h($j["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$q-1])?" autofocus":"").">";echo
input_hidden("fields[$q][orig]",$Kh);edit_type("fields[$q]",$j,$sb,$Rd);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$q][null]",1,$j["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$q'".($j["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$gc>".html_select("fields[$q][generated]",array_merge(["","DEFAULT","DEFAULT SQL"],driver()->generated),$j["generated"],"","label-default")." ";$b=" name='fields[$q][default]' aria-labelledby='label-default'";$Y=h($j["default"]);echo(preg_match('~\n~',$j["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$q][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$xb>".adminer()->commentInput('COLUMN',$b,$j["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$q]","+",'Add next')." ":""),($Kh==""||support("drop_col")?icon("cross","drop_col[$q]","x",'Remove'):"");}}function
process_fields(array&$k){if($_POST["add"]){$k=array_values($k);array_splice($k,key($_POST["add"]),0,[[]]);}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($Gc,$Mb,$Hc,$hl,$Ic,$z,$xg,$vg,$wg,$sh,$Yg){if($_POST["drop"])query_redirect($Gc,$z,$xg);elseif($sh=="")query_redirect($Mb,$z,$wg);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($z,$vg,queries($Gc)&&queries($Mb)&&driver()->commit());driver()->rollback();}elseif($sh!=$Yg){$Ob=queries($Mb);queries_redirect($z,$vg,$Ob&&queries($Gc));if($Ob&&$Hc)queries($Hc);}else
queries_redirect($z,$vg,queries($hl)&&queries($Ic)&&queries($Gc)&&queries($Mb));}function
create_trigger($vh,array$J){$nl=" $J[Timing] $J[Event]".(preg_match('~ OF~',$J["Event"])?" $J[Of]":"");return"CREATE TRIGGER ".idf_escape($J["Trigger"]).(JUSH=="mssql"?$vh.$nl:$nl.$vh).preg_replace('~[\s;]+$~',''," $J[Type]\n$J[Statement]").";";}function
q_dollar($Q){$mc='$$';while(strpos($Q.$mc,$mc)!=strlen($Q))$mc='$_'.substr($mc,1);return$mc.$Q.$mc;}function
routine_collate($rb){static$eb=[];if($rb&&!$eb){foreach(collations()as$db=>$km){foreach((array)$km
as$X)$eb[$X]=$db;}}return($eb[$rb]?"CHARACTER SET ".q($eb[$rb])." ":"")."COLLATE";}function
create_routine($wj,array$J){$O=[];$k=$J["fields"];ksort($k);foreach($k
as$j){if($j["field"]!=""){$bf=(preg_match("~^(".driver()->inout.")\$~",$j["inout"])?$j["inout"]:"");$O[]="\n  ".(JUSH=="mssql"?"@$j[field]".process_type($j).($bf?" $bf":""):($bf?"$bf ":"").idf_escape($j["field"]).process_type($j,routine_collate($j["collation"])));}}$jc="";$B=[];foreach(routine_options($wj)as$v=>$lm){$Y=idx($J["options"],$v,"");if($v=="DEFINER")$jc=($Y?" $v=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$lm){if($Y!="")$B[]="$v ".q($Y);}elseif($Y!=reset($lm)&&in_array($Y,$lm))$B[]=$Y;}$Ff=$J["language"];$kc=preg_replace('~[\s;]+$~','',$J["definition"]);$Cc=(JUSH=="pgsql"||($Ff&&$Ff!="sql"));$Wh=($O?implode(",",$O)."\n":"");return"CREATE$jc $wj ".table(trim($J["name"])).(JUSH=="mssql"&&$wj=="PROCEDURE"?rtrim($Wh):" ($Wh)").($wj=="FUNCTION"?"\nRETURNS".process_type($J["returns"],routine_collate($J["returns"]["collation"])):"").($Ff?" LANGUAGE $Ff":"").($B?"\n".implode(" ",$B):"").($Cc?" AS ".q_dollar("\n".trim($kc)."\n"):(JUSH=="mssql"?"\nAS":"")."\n$kc;");}function
remove_definer($G){$jc=implode("@",array_map('Adminer\idf_escape',explode("@",logged_user(),2)));return
preg_replace('(^([A-Z =]+) DEFINER='.preg_quote($jc).')','\1',$G);}function
object_name($U,$R,array$d){return
str_replace(["{table}","{columns}"],[$R,implode("_",$d)],adminer()->namePattern($U));}function
format_foreign_key(array$m,$A=""){$g=$m["db"];$fh=$m["ns"];return($A!=""?" CONSTRAINT ".idf_escape($A):"")." FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$m["source"])).") REFERENCES ".($g!=""&&$g!=$_GET["db"]?idf_escape($g).".":"").($fh!=""&&$fh!=$_GET["ns"]?idf_escape($fh).".":"").idf_escape($m["table"])." (".implode(", ",array_map('Adminer\idf_escape',$m["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$m["on_delete"])?" ON DELETE $m[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$m["on_update"])?" ON UPDATE $m[on_update]":"").($m["deferrable"]?" $m[deferrable]":"");}function
tar_file($l,$sl){$I=pack("a100a8a8a8a12a12",$l,644,0,0,decoct($sl->size),decoct(time()));$jb=8*32;for($q=0;$q<strlen($I);$q++)$jb+=ord($I[$q]);$I
.=sprintf("%06o",$jb)."\0 ";echo$I,str_repeat("\0",512-strlen($I));$sl->send();echo
str_repeat("\0",511-($sl->size+511)%512);}function
doc_version(){$Zj=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$Zj,$_);return($_[1]>=18?$_[1]:"19");}$hj=(JUSH=='sql'||connection()->flavor=='cockroach'?'~^\d+\.\d+~':'~^\d\.?\d~');$om=(preg_match($hj,$Zj,$_)?$_[0]:"");if(JUSH=='mssql')return($om>=15?"sql-server-ver$om":($om==12?"azuresqldb-current":"sql-server-2017"));return$om;}function
doc_link(array$ni,$il="📖"){$om=doc_version();$Zl=['sql'=>"https://dev.mysql.com/doc/refman/$om/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$om)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$om/",];if(connection()->flavor=='maria'){$Zl['sql']="https://mariadb.com/kb/en/";$ni['sql']=($ni['mariadb']?:str_replace(".html","/",$ni['sql']));}if(connection()->flavor=='cockroach'&&$ni['cockroach']){$Zl['pgsql']="https://docs.cockroachlabs.com/docs/v$om/";$ni['pgsql']=$ni['cockroach'];}return($ni[JUSH]?" <a href='".h($Zl[JUSH].$ni[JUSH].(JUSH=='mssql'?"?view=$om":""))."'".target_blank()." class='doc' title='".'Documentation'."'>$il</a>":"");}function
db_size($g){if(!connection()->select_db($g))return"?";$I=0;foreach(table_status()as$S)$I+=$S["Data_length"]+$S["Index_length"];return
adminer()->formatSizeValue($I);}function
set_utf8mb4($Mb){static$O=false;if(!$O&&preg_match('~\butf8mb4~i',$Mb)){$O=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'),null,301);if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!="")page_header('Database'.": ".h(DB),adminer()->error(),true,"","db");else{if(!isset($_GET["db"])&&support("single_db")){$f=adminer()->databases();if($f)redirect(ME."db=".url_escape($f[0]));}if($_POST["db"]&&!$i)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$i,false);echo"<p class='links'>\n";foreach(['database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',]as$v=>$X){if(support($v))echo"<a href='".h(ME)."$v='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$f=adminer()->databases();$Id=$_GET["filter"];if($Id!=""){foreach($f
as$v=>$g){if(stripos($g,$Id)===false)unset($f[$v]);}}if($f){$Hj=support("scheme");echo"<form method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<th>".'Collation'."<th>".'Tables'."<th>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$f=($_GET["dbsize"]?count_tables($f):array_flip($f));foreach($f
as$g=>$T){$vj=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($g);$r=h("Db-".$g);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$g,in_array($g,(array)$_POST["db"]),"","","",$r):""),"<th><a href='$vj' id='$r'>".h($g)."</a>";$rb=h(db_collation($g,[]));echo"<td>".(support("database")?"<a href='$vj".($Hj?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$rb</a>":$rb),"<td align='right'><a href='$vj&amp;schema=' id='tables-".h($g)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($g)."'>".($_GET["dbsize"]?db_size($g):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ja=adminer();$wi=($ja
instanceof
Plugins?$ja->plugins:[]);$Fc=($ja
instanceof
Plugins?$ja->drivers:[]);$rc=design_checksums();if($wi||$Fc||$rc){$kb=($ja
instanceof
Plugins?$ja->checksums():[]);$lh=Plugins::officialChecksums();$Ul=function($Yl){return" (<a href='$Yl'".target_blank()." class='update'>".VERSION."</a>)";};$vi=function($Ed)use($kb,$lh,$Ul){return($kb[$Ed]&&$lh[$Ed]&&$kb[$Ed]!==$lh[$Ed]?$Ul("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($wi
as$ti){$fj=new
\ReflectionObject($ti);$oc=Plugins::describe($ti);echo"<li><b>".h(Plugins::pluginName($ti))."</b>".($oc!=""?": $oc":"").$vi(basename((string)$fj->getFileName(),'.php'))."\n";}foreach($Fc
as$r=>$A)echo"<li><b>".h($r)."</b>: ".h($A).$vi(basename((string)$ja->driverFiles[$r],'.php'))."\n";if($rc){$nh=official_design_checksums();foreach($rc
as$l=>$qc){list($A,$jb)=$qc;$mh=$nh["$A/$l"];echo"<li><b>".h($l)."</b>".h($A?": $A":"").($mh&&$mh!==$jb?$Ul("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($Fb){$this->size+=strlen($Fb);fwrite($this->handler,$Fb);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$k=fields($a);header("Content-Type: application/octet-stream");$lm=array_merge((array)$_GET["where"],(array)$_GET["val"]);header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$lm)).".".friendly_url($_GET["field"]));$M=[idf_escape($_GET["field"])];$H=driver()->select($a,$M,[where($_GET,$k)],$M);$J=($H?$H->fetch_row():[]);echo
driver()->value($J[0],$k[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$k=fields($a);if(!$k)$i=adminer()->error();$S=table_status1($a);$A=adminer()->tableName($S);$i=$i?:h($S["Error"]);page_header(($k&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($A!=""?$A:h($a)),$i,[],"",!$k,($k?doc_link([JUSH=>driver()->tableHelp($a,is_view($S))]):""));$uj=[];foreach($k
as$v=>$j)$uj+=$j["privileges"];adminer()->selectLinks($S,(isset($uj["insert"])||!support("table")?"":null));$wb=$S["Comment"];if($wb!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$wb)."\n";if($k)adminer()->tableStructurePrint($k,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$J){$y=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($J["ns"]),ME);echo"<li><a href='".h($y."table=".url_escape($J["table"]))."'>".($J["ns"]!=$_GET["ns"]?"<b>".h($J["ns"])."</b>.":"").h($J["table"])."</a>";}echo"</ul>\n";}$Ze=driver()->inheritsFrom($a);if($Ze){echo"<h3>".'Inherits from'."</h3>\n";tables_links($Ze);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$u=indexes($a);if($u)adminer()->tableIndexesPrint($u,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$Rd=foreign_keys($a);$ua=function_exists('Adminer\alter_table');if($Rd){echo"<table>\n","<thead><tr><th>".'Source'."<th>".'Target'."<th>".'ON DELETE'."<th>".'ON UPDATE'.($ua?"<td class='hover'>":"")."<tbody>\n";foreach($Rd
as$A=>$m){echo"<tr".(!is_int($A)?" title='".h($A)."'":"").">","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$m["source"]))."</i>";$y=($m["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($m["db"]),ME):($m["ns"]!=""?preg_replace('~&ns=[^&]*~',"&ns=".url_escape($m["ns"]),ME):ME));echo"<td><a href='".h($y."table=".url_escape($m["table"]))."'>".($m["db"]!=""&&$m["db"]!=DB?"<b>".h($m["db"])."</b>.":"").($m["ns"]!=""&&$m["ns"]!=$_GET["ns"]?"<b>".h($m["ns"])."</b>.":"").h($m["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$m["target"]))."</i>)","<td>".h($m["on_delete"]),"<td>".h($m["on_update"]),($ua?'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($A)).'">'.'Alter'.'</a>':''),"\n";}echo"</table>\n";}echo($ua?'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n":''),"</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$gb=driver()->checkConstraints($a);if($gb){echo"<table>\n";foreach($gb
as$v=>$X)echo"<tr".($v!=$X?" title='".h($v)."'":"").">","<td><code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($v))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$Dl=triggers($a);if($Dl){echo"<table>\n";foreach($Dl
as$v=>$X){echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($v)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($v))."'>".'Alter'."</a>";$wj=$X[2];if($wj){$yj=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($wj["ns"]),ME).'function='.url_escape($wj["function"]).'&name='.url_escape($wj["name"]);echo", <a href='".h($yj)."' title='".h($wj["name"])."'>".'Alter function'."</a>";}echo"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$ek=driver()->shadowTables($a);if($ek){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($ek);}$Ye=driver()->inheritedTables($a);if($Ye){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$bi=driver()->partitionsInfo($a);if($bi)echo"<p><code class='sql jush-".JUSH."'>BY ".h("$bi[partition_by]($bi[partition])")."</code>\n";tables_links($Ye);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",[],h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$ej,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($ej,$R)as$A=>$gj){if($A!=$R)$d[$R]=max($d[$R],schema_column($A,$ej,$d)+1);}}return$d[$R];}function
type_class($U){foreach(['char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',]as$v=>$X){if(preg_match("~$v|$X~",$U))return" class='$v'";}}$Sk=[];$Uk=[];$Tk=[];$Bd=[];$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$cg,PREG_SET_ORDER);foreach($cg
as$q=>$_){$Sk[$_[1]]=[(float)$_[2],(float)$_[3]];$Uk[]="\n\t'".js_escape($_[1])."': [ $_[2], $_[3] ]";}$L=[];$ej=[];$Rd=[];$ra=driver()->allFields();$_e=[];$Vk=[];foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$Vk[$R]=$S;else$_e[$R]=true;}}foreach($Vk
as$R=>$S){$F=0;$L[$R]["fields"]=[];foreach($ra[$R]as$j){$F+=1.25;$Bd[$R][$j["field"]]=$F;$L[$R]["fields"][$j["field"]]=$j;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$_e[$X["table"]]){$Rd[$R][]=$X;$ej[$X["table"]][$R]=[];}}}$d=[];$ge=[];$_m=[];$le=[];foreach(array_keys($L)as$A)schema_column($A,$ej,$d);arsort($d);foreach($d
as$A=>$c){$Dg=null;foreach((array)idx($Rd,$A)as$X){if($X["table"]!=$A&&$L[$X["table"]])$Dg=($Dg===null?$d[$X["table"]]:min($Dg,$d[$X["table"]]));}$d[$A]=max($c,(int)$Dg-1);}$Xk=[];foreach($L
as$A=>$R){$c=$d[$A];$ge[$c][]=$A;$kl=.75*strlen($A);foreach($R["fields"]as$j)$kl=max($kl,.65*strlen($j["field"]));$Xk[$A]=ceil($kl)+1;$_m[$c]=max(idx($_m,$c,0),$Xk[$A]);}foreach($Rd
as$A=>$km){foreach($km
as$X){$ke=$d[$A]+(idx($d,$X["table"],$d[$A])>$d[$A]?1:0);$le[$ke]=idx($le,$ke,0)+1;}}ksort($ge);$ye=0;$zm=0;$ub=0;$Gi=null;$Qk=[];foreach($ge
as$c=>$T){if($Gi!==null){$ub=round($ub+$_m[$Gi]+1.7+idx($le,$c,0)*.1,1);$C=[];foreach($T
as$A){$Gk=0;$Lb=0;$Vg=array_keys((array)idx($ej,$A));foreach((array)idx($Rd,$A)as$X)$Vg[]=$X["table"];foreach($Vg
as$Pg){if($L[$Pg]&&$d[$Pg]<$c){$Gk+=$L[$Pg]["pos"][0];$Lb++;}}$C[$A]=($Lb?$Gk/$Lb:$ye);}asort($C);$T=array_keys($C);}$vl=0;foreach($T
as$A){$F=1.25*count($L[$A]["fields"]);$L[$A]["pos"]=($Sk[$A]?:[$vl,$ub]);$Qk[$A]=$L[$A]["pos"][1];$vl+=2.5+$F;$ye=max($ye,$L[$A]["pos"][0]+2.5+$F);$zm=max($zm,round($L[$A]["pos"][1]+$_m[$c],1));if(!$Sk[$A])$Tk[]="\n\t'".js_escape($A)."': [ ".$L[$A]["pos"][0].", ".$L[$A]["pos"][1]." ]";}$Gi=$c;}$Lf=[];$Ma=[];foreach($Rd
as$A=>$km){foreach($km
as$X){$dl=idx($Qk,$X["table"],$Qk[$A]);$ok=$Qk[$A]+$_m[$d[$A]];$tj=($dl-1>$ok);$Jf=($tj?$ok+1:min($Qk[$A],$dl)-1);$La=idx($Ma,(string)$Jf,0);$Ma[(string)$Jf]=$La+1;$Jf=round($tj?min($Jf+$La*.1,$dl-1):$Jf-$La*.1,1);while($Lf[(string)$Jf])$Jf-=.0001;$L[$A]["references"][$X["table"]][(string)$Jf]=[$X["source"],$X["target"]];$ej[$X["table"]][$A][(string)$Jf]=$X["target"];$Lf[(string)$Jf]=true;}}echo'<div id="schema" style="height: ',$ye,'em; width: ',$zm,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$Uk)."\n",'};
const tablePosDefault = {',implode(",",$Tk)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$ye,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($L
as$A=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$Xk[$A]."em;'>",'<a href="'.h(ME).'table='.url_escape($A).'"><b>'.h($A)."</b></a>";foreach($R["fields"]as$j){$X='<span'.type_class($j["type"]).' title="'.h($j["type"].($j["length"]?"($j[length])":"").($j["null"]?" NULL":'')).'">'.h($j["field"]).'</span>';echo"<br>".($j["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$el=>$gj){foreach($gj
as$Jf=>$bj){$Kf=$Jf-$R["pos"][1];$Dk=($Kf>0?"left: 100%; width: calc($Kf"."em - 100%)":"left: $Kf"."em");$zm=($Kf>0?"100%":(-$Kf)."em");$q=0;foreach($bj[0]as$nk)echo"\n<div class='references' title='".h($el)."' id='refs$Jf-".($q++)."' style='$Dk"."; top: ".$Bd[$A][$nk]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $zm;'></div></div>";}}foreach((array)$ej[$A]as$el=>$gj){foreach($gj
as$Jf=>$fl){$Kf=$Jf-$R["pos"][1];$q=0;foreach($fl
as$cl)echo"\n<div class='references arrow' title='".h($el)."' id='refd$Jf-".($q++)."' style='left: $Kf"."em; top: ".$Bd[$A][$cl]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$Kf)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($L
as$A=>$R){foreach((array)$R["references"]as$el=>$gj){if($L[$el]){foreach($gj
as$Jf=>$bj){$Eg=$ye;$kg=-10;foreach($bj[0]as$v=>$nk){$yi=$R["pos"][0]+$Bd[$A][$nk];$zi=$L[$el]["pos"][0]+$Bd[$el][$bj[1][$v]];$Eg=min($Eg,$yi,$zi);$kg=max($kg,$yi,$zi);}echo"<div class='references' id='refl$Jf' style='left: $Jf"."em; top: $Eg"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($kg-$Eg)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$i){$h=["auto_increment"=>''];foreach(["type","routine","event","trigger"]as$Ik){if(support($Ik))$h[$Ik."s"]='';}save_settings(array_intersect_key($_POST+$h,array_flip(["output","format","db_style","schema_style","table_style","data_style"])+$h),"adminer_export");$qa=(DB==""||$_GET["ns"]==="");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$sd=dump_headers((count($T)==1?key($T):DB),($qa||count($T)>1));$qf=preg_match('~sql~',$_POST["format"]);if($qf){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$Dk=$_POST["db_style"];$f=[DB];if(DB==""){$f=$_POST["databases"];if(is_string($f))$f=explode("\n",rtrim(str_replace("\r","",$f),"\n"));}foreach((array)$f
as$g){adminer()->dumpDatabase($g);if(connection()->select_db($g)){if($qf&&$Dk)echo
use_sql($g,$Dk).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?[""]:adminer()->schemas()))as$L){if($L!=""){if(DB==""&&information_schema(DB,$L))continue;set_schema($L);}if($qf&&$_POST["schema_style"]&&function_exists('Adminer\use_schema_sql'))echo
use_schema_sql($_GET["ns"],$_POST["schema_style"]).";\n\n";$_k=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):[]);$rd=[];$Yb=[];foreach($_k
as$A=>$S){if($qa||in_array($A,(array)$_POST["tables"]))$rd[$A]=$S;if($qa||in_array($A,(array)$_POST["data"]))$Yb[$A]=$S;}if($qf){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($rd);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$El=[];foreach($Yb
as$A=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($rd[$A])))$El[]=$A;}echo
truncate_all_sql($El);}$Rh="";if($_POST["types"]){foreach(types()as$r=>$U){$kc=type_definition($r);$ih=($kc["kind"]=='d'?"DOMAIN":"TYPE");if($kc["definition"])$Rh
.=($Dk!='DROP+CREATE'?"DROP $ih IF EXISTS ".table($U).";;\n":"")."CREATE $ih ".table($U)." $kc[definition];\n\n";else$Rh
.="-- Could not export type ".preg_replace('~[\r\n]+~',' ',$U)."\n\n";}}if($_POST["routines"]){foreach(routines()as$J){$A=$J["ROUTINE_NAME"];$wj=$J["ROUTINE_TYPE"];$Xe=routine($J["SPECIFIC_NAME"],$wj);if($Xe["definition"]!=""){$Mb=create_routine($wj,["name"=>$A]+$Xe);set_utf8mb4($Mb);$Rh
.=($Dk!='DROP+CREATE'?"DROP $wj IF EXISTS ".table($A).";;\n":"")."$Mb;\n\n";}else$Rh
.="-- Could not export ".strtolower($wj)." ".preg_replace('~[\r\n]+~',' ',$A)."\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$J){$Mb=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($J["Name"]),3));set_utf8mb4($Mb);$Rh
.=($Dk!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($J["Name"]).";;\n":"")."$Mb;;\n\n";}}echo($Rh&&JUSH=='sql'?"DELIMITER ;;\n\n$Rh"."DELIMITER ;\n\n":$Rh);}if($_POST["table_style"]||$_POST["data_style"]){$qm=[];foreach($_k
as$A=>$S){$R=array_key_exists($A,$rd);$Wb=array_key_exists($A,$Yb);if($R||$Wb){$sl=null;if($sd=="tar"){$sl=new
TmpFile;ob_start([$sl,'write'],1e5);}adminer()->dumpTable($A,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$qm[]=$A;elseif($Wb){$k=fields($A);$M=["*"];$Ib=convert_fields($k,$k);if($Ib)$M[]=substr($Ib,2);adminer()->dumpData($A,$_POST["data_style"],"",$M);}if($qf&&$_POST["triggers"]&&$R&&($Dl=trigger_sql($A)))echo"\nDELIMITER ;;\n$Dl\nDELIMITER ;\n";if($sd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$g/")."$A.csv",$sl);}elseif($qf)echo"\n";}}if($qf&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($rd
as$A=>$S){if(!is_view($S))echo
foreign_keys_sql($A);}}if($qf){foreach($qm
as$pm)adminer()->dumpTable($pm,$_POST["table_style"],1);}if($sd=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$i,($_GET["export"]!=""?["table"=>$_GET["export"]]:[]),h(DB));echo'
<form method="post">
<table class="layout">
';$cc=['','USE','DROP+CREATE','CREATE'];$Fj=(JUSH=="mssql"?['','DROP+CREATE','CREATE']:$cc);$Wk=['','DROP+CREATE','CREATE'];$Xb=['','TRUNCATE+INSERT','INSERT'];if(JUSH=="sql")$Xb[]='INSERT+UPDATE';$J=get_settings("adminer_export");if(!$J)$J=["output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"schema_style"=>"","table_style"=>"DROP+CREATE","data_style"=>"INSERT"];echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$J["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$J["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$cc,$J["db_style"]).(support("type")?checkbox("types",1,$J["types"],'User types'):"").(support("routine")?checkbox("routines",1,$J["routines"],'Routines'):"").(support("event")?checkbox("events",1,$J["events"],'Events'):"")),(function_exists('Adminer\use_schema_sql')?"<tr><th>".'Schema'."<td>".html_select('schema_style',$Fj,$J["schema_style"]):""),"<tr><th>".'Tables'."<td>".html_select('table_style',$Wk,$J["table_style"]).checkbox("auto_increment",1,$J["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$J["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Xb,$J["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$Fi=[];if($_GET["ns"]===""&&support("scheme")){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$L){if(!information_schema(DB,$L))echo"<tr><td>".checkbox("schemas[]",$L,true,$L,"","block")."\n";}}elseif(DB!=""){$hb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$hb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$hb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$qm="";$Zk=tables_list();foreach($Zk
as$A=>$U){$Ei=preg_replace('~_.*~','',$A);$hb=($a==""||$a==(substr($a,-1)=="%"?"$Ei%":$A));$Ji="<tr><td>".checkbox("tables[]",$A,$hb,$A,"","block");if($U!==null&&!preg_match('~table~i',$U))$qm
.="$Ji\n";else
echo"$Ji<td align='right'><label class='block'><span id='Rows-".h($A)."'></span>".checkbox("data[]",$A,$hb)."</label>\n";$Fi[$Ei]++;}echo$qm;if($Zk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$f=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($f?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($f){foreach($f
as$g){if(!information_schema($g)){$Ei=preg_replace('~_.*~','',$g);echo"<tr><td>".checkbox("databases[]",$g,$a==""||$a=="$Ei%",$g,"","block")."\n";$Fi[$Ei]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$Kd=true;foreach($Fi
as$v=>$X){if($v!=""&&$X>1){echo($Kd?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$v%")."'>".h($v)."</a>";$Kd=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$H=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$ee=$H;if(!$H)$H=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($ee?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($J=$H->fetch_assoc())echo'<tr><td>'.h($J["User"]),"<td>".h($J["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($J["User"]).'&host='.url_escape($J["Host"])).'">'.'Edit'."</a>\n";if(!$ee||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$i&&$_POST["export"]){save_settings(["output"=>$_POST["output"],"format"=>$_POST["format"]],"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}if(!$i&&$_POST["val"]){$ma=0;$Ek=true;$ab=[];$Cj=0;foreach($_POST["val"]as$K)$Cj+=count($K);$Oa=$Cj>1&&driver()->begin();foreach($_POST["val"]as$Ok=>$K){$R=bracket_escape($Ok,true);$k=fields($R);$Pk=indexes($R);foreach($K
as$s=>$J){parse_str(bracket_escape($s,true),$Z);$Ml=[];foreach($Z["where"]as$v=>$X)$Ml[bracket_escape($v,true)]=$X;if(!$k||$Z["null"]||array_diff_key($Ml,$k)||!unique_array($Ml,$Pk)){$Ek=false;break
2;}$O=[];$M=[];foreach($J
as$zf=>$X){$v=bracket_escape($zf,true);$j=idx($k,$v);if(!$j){$Ek=false;break
3;}$O[idf_escape($v)]=(preg_match('~char|text~',$j["type"])||$X!=""?adminer()->processInput($j,$X):"NULL");$M[$zf]=$v;}$Vi=where($Z,$k);if(!driver()->update($R,$O," WHERE $Vi",0," ")){$Ek=false;break
2;}$ma+=connection()->affected_rows;$d=[];foreach($M
as$v)$d[]=idf_escape($v);$Vl=driver()->select($R,$d,[$Vi],$d);$Zg=($Vl?$Vl->fetch_row():[]);$uf=0;foreach($M
as$zf=>$v){$j=$k[$v];$Ck=['type'=>(preg_match('~binary~',$j["type"])?'blob':$j["type"])];$ab["val[$Ok][$s][$zf]"]=select_value(idx($Zg,$uf++),"",$Ck,null);}}}if($Oa&&$Ek)$Ek=driver()->commit();queries_redirect(null,lang_format(['%d item has been affected.','%d items have been affected.'],$ma),$Ek);if($Oa&&!$Ek)driver()->rollback();page_headers();page_messages($i);foreach($ab
as$A=>$X)echo"<div data-name='".h($A)."' hidden>$X</div>\n";exit;}restart_session();$De=&get_session("queries");$Ce=&$De[DB];if(!$i&&$_POST["clear"]){$Ce=[];redirect(remove_from_uri("history"));}stop_session();$ka=get_settings("adminer_import");if($_POST&&$ka)save_settings($ka,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$i);$Tf=driver()->lineComment();$dd=0;function
query_error_line($il,$oh,$G,$Sf){return
substr_count(substr($il,0,$oh),"\n")+($Sf?:substr_count(substr($G,0,strspn($G," \t\r\n")),"\n")+1);}if(!$i&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$mc=driver()->delimiter;$n=false;if(!isset($_GET["import"]))$G=$_POST["query"];elseif($_POST["webfile"]){$rk=adminer()->importServerPath();$n=@fopen((file_exists($rk)?$rk:"compress.zlib://$rk.gz"),"rb");$G=($n?fread($n,1e6):false);}else$G=get_file("sql_file",true,$mc);if(is_string($G)){if(($sg=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($sg,strval(2*strlen($G)+memory_get_usage()+8e6)));if($G!=""&&strlen($G)<1e6){$Qi=$G.(preg_match("~$mc\\s*\$~",$G)?"":$mc);if(!$Ce||first(end($Ce))!=$Qi){restart_session();$Ce[]=[$Qi,time()];set_session("queries",$De);stop_session();}}$pk="(?:\\s|\xEF\xBB\xBF|/\\*[\s\S]*?\\*/|(?:$Tf)[^\n]*\n?|--\r?\n)";$oh=0;$Ui=0;$Uc=true;$Kb=false;$zg=false;$Bb=connect();if($Bb&&DB!=""){$Bb->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$Bb);}$vb=0;$ed=[];$Zh='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$Tf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$wl=microtime(true);while($G!=""){if(!$oh&&preg_match("~^$pk*+DELIMITER\\s+(\\S+)~i",$G,$_)){$mc=preg_quote($_[1]);$G=substr($G,strlen($_[0]));$Ui+=strlen($_[0]);}elseif(!$oh&&JUSH=='pgsql'&&preg_match("~^($pk*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$G,$_)){$mc="\n\\\\\\.\r?\n";$Kb=true;$oh=strlen($_[0]);}elseif(!$oh&&JUSH=='pgsql'&&preg_match("~^$pk*+\\\\connect[ \t][^\n]*~",$G,$_)){$mc="\n";$zg=true;$oh=strlen($_[0]);}else{preg_match("($mc\\s*|$Zh)",$G,$_,PREG_OFFSET_CAPTURE,$oh);list($Td,$F)=$_[0];if(!$Td&&$n&&!feof($n))$G
.=fread($n,1e5);else{if(!$Td&&rtrim($G)=="")break;$oh=$F+strlen($Td);if($Td&&!preg_match("(^$mc)",$Td)){$Wa=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($F>0&&strtolower($G[$F-1])=="e"));$oi=($Td=='/*'?'\*/':($Td=='['?']':(preg_match("~^(?:$Tf)~",$Td)?"\n":preg_quote($Td).($Wa?'|\\\\.':''))));while(preg_match("($oi|\$)s",$G,$_,PREG_OFFSET_CAPTURE,$oh)){$Dj=$_[0][0];if(!$Dj&&$n&&!feof($n))$G
.=fread($n,1e5);else{$oh=$_[0][1]+strlen($Dj);if(!$Dj||$Dj[0]!="\\")break;}}}else{$Qi=substr($G,0,$F+($Kb?3:0));$G=substr($G,$oh);$Ri=$Ui;$Ui+=$oh;$oh=0;if($Kb||$zg){$mc=driver()->delimiter;$Kb=false;$zg=false;}$ob="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($Qi)."</code>";if(preg_match("~^$pk*+\$~",$Qi)&&!preg_match('~/\*M?!~',$Qi)){echo($_POST["only_errors"]?"":"<pre>$ob</pre>\n");continue;}$Uc=false;$vb++;$Ji="<pre id='sql-$vb'>$ob</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$pk*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Qi,$_)!==0){echo$Ji,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$_[1])?'ATTACH':'VACUUM INTO')."\n";$ed[]=" <a href='#sql-$vb'>$vb</a>";if(!$dd&&!isset($_GET["import"]))$dd=query_error_line($_POST["query"],$Ri,$Qi,0);if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Ji;ob_flush();flush();}$wk=microtime(true);if(JUSH=="pgsql"&&preg_match("~^$pk*+\\\\connect\\s+(\"(?:[^\"]|\"\")+\"|[^\\s\";]+)\\s*;*\\s*\$~",$Qi,$_)){$g=idf_unescape($_[1]);if(connection()->select_db($g)){if($Bb)$Bb->select_db($g);echo($_POST["only_errors"]?"":"<p class='message'>".'Database'.": ".h($g)." <span class='time'>(".format_time($wk).")</span>\n");continue;}}elseif(connection()->multi_query($Qi)&&$Bb&&preg_match("~^$pk*+USE\\b~i",$Qi))$Bb->query($Qi);do{$H=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$Ji:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$ed[]=" <a href='#sql-$vb'>$vb</a>";if(!$dd&&!isset($_GET["import"]))$dd=query_error_line($_POST["query"],$Ri,$Qi,driver()->errorLine($Qi));if($_POST["error_stops"])break
2;}else{$y=ME."sql=".url_escape(trim($Qi));$ll=" <span class='time'>(".format_time($wk).")</span>".(strlen($y)<adminer()->sqlUrlLength()-100?" <a href='".h($y)."'>".'Edit'."</a>":"");$ma=connection()->affected_rows;$tm=($_POST["only_errors"]?"":driver()->warnings());$um="warnings-$vb";if($tm)$ll
.=", <a href='#$um' class='toggle'>".'Warnings'."</a>";$pd="";$qd="explain-$vb";if(is_object($H)){$x=$_POST["limit"];$gh=$x;$Lc=!$_POST["only_errors"];if($Lc)echo"<form method='post'>\n";$Jh=print_select_result($H,$Bb,[],$gh,$Lc);if(!$_POST["only_errors"]){$gh=max($H->num_rows,$gh);echo"<p class='sql-footer'>".($gh?($x&&$gh>$x?sprintf('%d / ',$x):"").lang_format(['%d row','%d rows'],$gh):""),$ll;if($Bb&&preg_match("~^($pk|\\()*+SELECT\\b~i",$Qi)&&($pd=adminer()->explain($Bb,$Qi,$Jh))!="")echo", <a href='#$qd' class='toggle'>Explain</a>";if($Lc)echo", <input type='submit' name='save' value='".'Save'."' class='jsonly' disabled"." title='".'Ctrl+click on a value to modify it.'."'".on('click','sqlSave','Saving…').">";$r="export-$vb";echo", <a href='#$r' class='toggle'>".'Export'."</a><span id='$r' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ka["output"])." ".html_select("format",adminer()->dumpFormat(),$ka["format"]).input_hidden("query",$Qi)."<input type='submit' name='export' value='".'Export'."'".($x?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$pk*+(CREATE|DROP|ALTER)$pk++(DATABASE|SCHEMA)\\b~i",$Qi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(['Query executed OK, %d row affected.','Query executed OK, %d rows affected.'],$ma)."$ll\n";}echo($tm?"<div id='$um' class='hidden'>\n$tm</div>\n":""),($pd!=""?"<div id='$qd' class='hidden explain'>\n$pd</div>\n":"");}$wk=microtime(true);}while(connection()->next_result());}}}}}if($Uc)echo"<p class='message'>".'No commands to execute.'."\n";else{$Re=connection()->inTransaction();driver()->rollback();if($Re)echo"<pre><code class='jush-".JUSH."'>ROLLBACK".(JUSH=="mssql"?" TRANSACTION":"")." -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(['%d query executed OK.','%d queries executed OK.'],$vb-count($ed))," <span class='time'>(".format_time($wl).")</span>\n";elseif($ed&&$vb>1)echo"<p class='error'>".'Error in query'.": ".implode("",$ed)."\n";}}else
echo"<p class='error'>".upload_error($G)."\n";}echo'
<form method="post" enctype="multipart/form-data" id="form"';$Wl="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"),adminer()->sqlUrlLength());else
echo
on_upload_progress($Wl);echo'>
';$md="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$Qi=$_GET["sql"];if($_POST)$Qi=$_POST["query"];elseif($_GET["history"]=="all")$Qi=$Ce;elseif($_GET["history"]!="")$Qi=idx($Ce[$_GET["history"]],0);echo"<p>";textarea("query",$Qi,20,80,JUSH,$dd);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$md\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$me=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($Wl?input_hidden(ini_get("session.upload_progress.name"),$Wl):""),"SQL$me: ".file_input(" name='sql_file[]' multiple","\n$md"),($Wl?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$Oe=adminer()->importServerPath();if($Oe)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($Oe)."$me</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$Ce){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($Ce);$X;$X=prev($Ce)){$v=key($Ce);list($Qi,$ll,$Qc)=$X;echo'<div><a href="'.h(ME."sql=&history=$v").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$ll)."'>".@date("H:i:s",$ll)."</span>"." <code class='sql jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$Tf).*~m",'',$Qi))),80,"</code>").($Qc?" <span class='time'>($Qc)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$k=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$k):""):where($_GET,$k));$Tl=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($k
as$A=>$j){if((!$Tl&&!isset($j["privileges"]["insert"]))||adminer()->fieldName($j)=="")unset($k[$A]);}if($_POST&&!$i&&!isset($_GET["select"])){$z=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$z=($Tl?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$z))$z=ME."select=".url_escape($a);$u=indexes($a);$Nl=unique_array($_GET["where"],$u);$Vi="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($z,'Item has been deleted.',driver()->delete($a,$Vi,$Nl?0:1));else{$O=[];foreach($k
as$A=>$j){$X=process_input($j);if($X!==false&&$X!==null)$O[idf_escape($A)]=$X;}if($Tl){if(!$O)redirect($z);queries_redirect($z,'Item has been updated.',driver()->update($a,$O,$Vi,$Nl?0:1));if(is_ajax()){page_headers();page_messages($i);exit;}}else{$H=driver()->insert($a,$O);$If=($H?last_id($H):0);queries_redirect($z,sprintf('Item%s has been inserted.',($If?" $If":"")),$H);}}}$J=null;$G="";$ll="";if($Z){$M=[];$Pj=["*"];foreach($k
as$A=>$j){if(isset($j["privileges"]["select"])){$_a=($_POST["clone"]&&$j["auto_increment"]?"''":convert_field($j));$c=($_a?"$_a AS ":"").idf_escape($A);$M[]=$c;if($_a)$Pj[]=$c;}}$J=[];if(!support("table")){$M=["*"];$Pj=$M;}if($M){$wk=microtime(true);$H=driver()->select($a,$M,[$Z],$M,[],(isset($_GET["select"])?2:1));$G=str_replace("SELECT ".implode(", ",$M),"SELECT ".implode(", ",$Pj),driver()->query);$ll=format_time($wk);if(!$H)$i=adminer()->error();else{$J=$H->fetch_assoc();if(!$J)$J=false;}if(isset($_GET["select"])&&(!$J||$H->fetch_assoc()))$J=null;}}if(!$k&&driver()->primary!=""){if(!$Z){$H=driver()->select($a,["*"],[],["*"]);$J=($H?$H->fetch_assoc():false);if(!$J)$J=[driver()->primary=>""];}if($J){foreach($J
as$v=>$X){if(!$Z)$J[$v]=null;$k[$v]=["field"=>$v,"null"=>($v!=driver()->primary),"auto_increment"=>($v==driver()->primary)];}}}if($_POST["save"]){$_i=[];foreach((array)$_POST["fields"]as$v=>$X)$_i[bracket_escape($v,true)]=$X;$J=$_i+($J?$J:[]);}edit_form($a,$k,$J,$Tl,$i,$G,$ll);}elseif(isset($_GET["create"])){function
referencable_primary($Rj){$I=[];foreach(table_status('',true)as$Rk=>$R){if($Rk!=$Rj&&!$R["dependent"]&&fk_support($R)){foreach(fields($Rk)as$j){if($j["primary"]){if($I[$Rk]){unset($I[$Rk]);break;}$I[$Rk]=$j;}}}}return$I;}$a=$_GET["create"];$di=driver()->partitionBy;$hi=($di&&$a!=""?driver()->partitionsInfo($a):[]);$dj=referencable_primary($a);$Rd=[];foreach($dj
as$Rk=>$j)$Rd[str_replace("`","``",$Rk)."`".str_replace("`","``",$j["field"])]=$Rk;$Mh=[];$S=[];$eh=false;if($a!=""){$Mh=fields($a);$S=table_status1($a);$eh=(count($S)<2);}$ua=($a==""||driver()->supportsAlterTable($S));$J=$_POST;$J["fields"]=(array)$J["fields"];if($J["auto_increment_col"])$J["fields"][$J["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$i)save_settings(["comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]]);if($_POST&&!process_fields($J["fields"])&&!$i){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables([$a]));else{$k=[];$ra=[];$am=false;$Pd=[];$Lh=reset($Mh);$oa=" FIRST";foreach($J["fields"]as$j){$m=$Rd[$j["type"]];$Gl=($m!==null?$dj[$m]:$j);if($j["field"]!=""){if(!$j["generated"])$j["default"]=null;$j["default_sql"]=($j["generated"]=="DEFAULT SQL");$Oi=process_field($j,$Gl);$ra[]=[$j["orig"],$Oi,$oa];if(!$Lh||$Oi!==process_field($Lh,$Lh)){$k[]=[$j["orig"],$Oi,$oa];if($j["orig"]!=""||$oa||(JUSH=="sqlite"&&$j["comment"]!=""))$am=true;}if($m!==null)$Pd[idf_escape($j["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(['table'=>$Rd[$j["type"]],'source'=>[$j["field"]],'target'=>[$Gl["field"]],'on_delete'=>$j["on_delete"],],object_name("FOREIGN",trim($J["name"]),[$j["field"]]));$oa=" AFTER ".idf_escape($j["field"]);}elseif($j["orig"]!=""){$am=true;$k[]=[$j["orig"]];}if($j["orig"]!=""){$Lh=next($Mh);if(!$Lh)$oa="";}}$fi=[];if(in_array($J["partition_by"],$di)){foreach($J
as$v=>$X){if(preg_match('~^partition~',$v))$fi[$v]=$X;}foreach($fi["partition_names"]as$v=>$A){if($A==""){unset($fi["partition_names"][$v]);unset($fi["partition_values"][$v]);}}$fi["partition_names"]=array_values($fi["partition_names"]);$fi["partition_values"]=array_values($fi["partition_values"]);if($fi==$hi)$fi=[];}elseif(preg_match("~partitioned~",$S["Create_options"]))$fi=null;$ug='Table has been altered.';if($a==""){cookie("adminer_engine",$J["Engine"]);$ug='Table has been created.';}$A=trim($J["name"]);$z=ME.(support("table")?"table=":"select=").url_escape($A);$wb=($J["Comment"]!=$S["Comment"]?$J["Comment"]:null);$Xc=($J["Engine"]&&$J["Engine"]!=$S["Engine"]?$J["Engine"]:"");$H=alter_table($a,$A,(JUSH=="sqlite"&&($am||$Pd||$wb!==null||$Xc)?$ra:$k),$Pd,$wb,$Xc,($J["Collation"]&&$J["Collation"]!=$S["Collation"]?$J["Collation"]:""),($J["Auto_increment"]!=""?number($J["Auto_increment"]):""),$fi);if($H&&!Queries::$queries&&$a!=""&&!$k&&!$Pd)redirect($z);queries_redirect($z,$ug,$H);}}$yk=($a!=""?"alter":"create");page_header(($a!=""?'Alter table':'Create table'),$i,["table"=>$a],h($a),$eh,doc_link(['sql'=>"$yk-table.html",'mariadb'=>($a!=""?"$yk-table":""),]));if(!$_POST){$Jl=driver()->types();$J=["Engine"=>$_COOKIE["adminer_engine"],"fields"=>[["field"=>"","type"=>(isset($Jl["int"])?"int":(isset($Jl["integer"])?"integer":"")),"on_update"=>""]],"partition_names"=>[""],];if($a!=""){$J=$S;$J["name"]=$a;$J["fields"]=[];if(!$_GET["auto_increment"])$J["Auto_increment"]="";foreach($Mh
as$j){if($j["generated"])$j["default"]=ltrim($j["default"]);$j["generated"]=$j["generated"]?:(isset($j["default"])?($j["default_sql"]?"DEFAULT SQL":"DEFAULT"):"");$J["fields"][]=$j;}if($di){$J+=$hi;$J["partition_names"][]="";$J["partition_values"][]="";}}}$sb=flat_collations();$Yc=driver()->engines();foreach($Yc
as$Xc){if(!strcasecmp($Xc,$J["Engine"])){$J["Engine"]=$Xc;break;}}$fg=max_input_vars(12,20);if($fg){$_e=(count($J["fields"])>$fg?"":" hidden");echo"<p".($_e?" id='max-fields' data-columns='$fg'":"")." class='error$_e'>".max_input_vars_error()."\n";}echo'
<form method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($J["name"])."' autocapitalize='off'>\n",(!$ua?h($S["Engine"])."\n":($Yc?html_select("Engine",[""=>"(".'engine'.")"]+$Yc,$J["Engine"],on('change','helpClose').on_help_value())."\n":""));if($sb)echo"<datalist id='collations'>".optionlist($sb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($J["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ua){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($J["fields"],$sb,"TABLE",$Rd);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($J["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',6),"jsonly");$yb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$yb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($yb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$J["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($di&&(JUSH=='sql'||$a=="")){$ei=preg_match('~RANGE|LIST~',$J["partition_by"]);print_fieldset("partition",'Partition by',$J["partition_by"]);echo"<p>".html_select("partition_by",array_merge([""],$di),$J["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($J["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($ei||!$J["partition_by"]?" hidden":"")."' value='".h($J["partitions"])."'>\n","<table id='partition-table'".($ei?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($J["partition_names"]as$v=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($v==count($J["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($J["partition_values"],$v)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$We=["PRIMARY","UNIQUE","INDEX"];$S=table_status1($a,true);$Ue=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$We[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$We[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$We[]="VECTOR";$u=indexes($a);$k=fields($a);$Ii=[];if(JUSH=="mongo"){$Ii=$u["_id_"];unset($We[0]);unset($u["_id_"]);}$J=$_POST;if($J)save_settings(["index_options"=>$J["options"]]);if($_POST&&!$i&&!$_POST["add"]&&!$_POST["drop_col"]){$ta=[];foreach($J["indexes"]as$t){$A=$t["name"];if(in_array($t["type"],$We)){$d=[];$Pf=[];$pc=[];$zh=[];$Ve=(support("partial_indexes")?$t["partial"]:"");$Te=(in_array($t["algorithm"],$Ue)?$t["algorithm"]:"");$O=[];ksort($t["columns"]);foreach($t["columns"]as$v=>$c){if($c!=""){$w=idx($t["lengths"],$v);$nc=idx($t["descs"],$v);$yh=idx($t["opclasses"],$v);$O[]=($k[$c]?idf_escape($c):$c).($w?"(".(+$w).")":"").($yh!=""?" ".idf_escape($yh):"").($nc?" DESC":"");$d[]=$c;$Pf[]=($w?:null);$pc[]=$nc;$zh[]="$yh";}}$nd=$u[$A];if($nd){ksort($nd["columns"]);ksort($nd["lengths"]);ksort($nd["descs"]);if($t["type"]==$nd["type"]&&array_values($nd["columns"])===$d&&(!$nd["lengths"]||array_values($nd["lengths"])===$Pf)&&array_values($nd["descs"])===$pc&&(!$nd["opclasses"]||array_values($nd["opclasses"])===$zh)&&$nd["partial"]==$Ve&&(!$Ue||$nd["algorithm"]==$Te)&&!$nd["invalid"]){unset($u[$A]);continue;}}if($d)$ta[]=[$t["type"],$A,$O,$Te,$Ve];}}foreach($u
as$A=>$nd)$ta[]=[$nd["type"],$A,"DROP"];if(!$ta)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$ta));}page_header('Indexes',$i,["table"=>$a],h($a),false,doc_link(['sql'=>"create-index.html",]));$Dd=array_keys($k);if($_POST["add"]){foreach($J["indexes"]as$v=>$t){if($t["columns"][count($t["columns"])]!="")$J["indexes"][$v]["columns"][]="";}$t=end($J["indexes"]);if($t["type"]||array_filter($t["columns"],'strlen'))$J["indexes"][]=["columns"=>[1=>""]];}if(!$J){foreach($u
as$v=>$t){$u[$v]["name"]=$v;$u[$v]["columns"][]="";}$u[]=["columns"=>[1=>""]];$J["indexes"]=$u;}$Pf=(JUSH=="sql"||JUSH=="mssql");$zh=driver()->indexOpclasses();$fk=($_POST?$_POST["options"]:get_setting("index_options"));$Rg=[];foreach($We
as$U)$Rg[$U]=str_replace("{table}",$a,adminer()->namePattern($U));echo'
<form method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$Me=" class='idxopts".($fk?"":" hidden")."'";if($Ue)echo"<th id='label-algorithm'$Me>".'Algorithm'.doc_link(['sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',]);echo'<th><input type="submit" hidden>','Columns'.($Pf?"<span$Me> (".'length'.")</span>":"");if($Pf||support("descidx"))echo
checkbox("options",1,$fk,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$Me>".'Condition';echo'<td><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($Ii){echo"<tr><td>PRIMARY<td>";foreach($Ii["columns"]as$v=>$c)echo
select_input(" disabled",array_combine($Dd,$Dd),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$uf=1;foreach($J["indexes"]as$t){if(!$_POST["drop_col"]||$uf!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$uf][type]",[-1=>""]+$We,$t["type"],on('change','indexesChangeType',$Rg),"label-type");if($Ue)echo"<td$Me>".html_select("indexes[$uf][algorithm]",array_merge([""],$Ue),$t['algorithm'],"","label-algorithm");echo"<td>";ksort($t["columns"]);$q=1;foreach($t["columns"]as$v=>$c){echo"<span>".select_input(" name='indexes[$uf][columns][$q]' title='".'Column'."'".on('change','indexesChangeColumn',$Rg),($k&&($c==""||$k[$c])?array_combine($Dd,$Dd):[]),$c)," <span$Me>",($Pf?"<input type='number' name='indexes[$uf][lengths][$q]' class='size' value='".h(idx($t["lengths"],$v))."' title='".'Length'."'>":"");if($zh){$yh=idx($t["opclasses"],$v);echo
html_select("indexes[$uf][opclasses][$q]",[""=>"(".'operator class'.")"]+array_combine($zh,$zh)+($yh!=""?[$yh=>$yh]:[]),$yh),'';}echo(support("descidx")?checkbox("indexes[$uf][descs][$q]",1,idx($t["descs"],$v),'descending'):""),"<br>","</span></span>";$q++;}echo"<td><input name='indexes[$uf][name]' value='".h($t["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$Me><input name='indexes[$uf][partial]' value='".h($t["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$uf]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]')),(idx($u[$t["name"]],"invalid")?" <span class='error'>INVALID</span>":""),"\n";}$uf++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
';if(support("concurrent_indexes"))echo
checkbox("concurrently",1,$_POST["concurrently"],"concurrently").''."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["database"])){$J=$_POST;if($_POST&&!$i&&!$_POST["add"]){$A=trim($J["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases([DB]));}elseif($A!==DB){if(DB!=""){$_GET["db"]=$A;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($A),'Database has been renamed.',rename_database($A,(string)$J["collation"]));}else{$f=explode("\n",str_replace("\r","",$A));$Ek=true;$Gf="";foreach($f
as$g){if(count($f)==1||$g!=""){if(!create_database($g,(string)$J["collation"]))$Ek=false;$Gf=$g;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($Gf),'Database has been created.',$Ek);}}else{if(!$J["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($A).(preg_match('~^[a-z0-9_]+$~i',$J["collation"])?" COLLATE $J[collation]":""),substr(ME,0,-1),'Database has been altered.');}}$yk=(DB!=""?"alter":"create");page_header(DB!=""?'Alter database':'Create database',$i,[],h(DB),false,doc_link(['sql'=>"$yk-database.html",'mariadb'=>(DB!=""?"":"$yk-database"),]));$sb=collations();$A=DB;if($_POST)$A=$J["name"];elseif(DB!="")$J["collation"]=db_collation(DB,$sb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$ee){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$ee,$_)&&$_[1]){$A=stripcslashes(idf_unescape("`$_[2]`"));break;}}}echo'
<form method="post">
<p>
',($_POST["add"]||strpos($A,"\n")?"<textarea autofocus name='name' rows='10' cols='40'>\n".h($A).'</textarea><br>':'<input name="name" autofocus value="'.h($A).'" data-maxlength="64" autocapitalize="off">')."\n",($sb?"<datalist id='collations'>".optionlist(flat_collations())."</datalist>"."<input list='collations' name='collation' value='".h($J["collation"])."' placeholder='(".'collation'.")'>".doc_link(['sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",]):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);$Aj=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$wj=routine($_GET["call"],$Aj);$rf=(idx($wj["returns"],"type")=="trigger");page_header(($rf?'Triggers':'Call').": ".h($ba),$i,"#routines","",!$wj,(isset($_GET["callf"])?"":doc_link(['sql'=>"call.html",])));$Pe=[];$Rh=[];foreach($wj["fields"]as$q=>$j){if(substr($j["inout"],-3)=="OUT"&&JUSH=='sql')$Rh[$q]="@".idf_escape($j["field"])." AS ".idf_escape($j["field"]);if(!$j["inout"]||preg_match('~^(IN|OUTPUT)~',$j["inout"]))$Pe[]=$q;}if(!$i&&$_POST){$Xa=[];foreach($wj["fields"]as$v=>$j){$X="";if(in_array($v,$Pe)){$X=process_input($j);if($X===false)$X="''";if(isset($Rh[$v]))connection()->query("SET @".idf_escape($j["field"])." = $X");}if(isset($Rh[$v]))$Xa[]="@".idf_escape($j["field"]);elseif(in_array($v,$Pe))$Xa[]=$X;}$ya=implode(", ",$Xa);$G=(isset($_GET["callf"])||JUSH!="mssql"?(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($wj["returns"],"type")=="record"?"* FROM ":"").table($ba)."($ya)":"EXEC ".table($ba).($ya!=""?" $ya":""));$wk=microtime(true);$H=connection()->multi_query($G);$ma=connection()->affected_rows;echo
adminer()->selectQuery($G,$wk,!$H);if(!$H)echo"<p class='error'>".adminer()->error()."\n";else{$Bb=connect();if($Bb)$Bb->select_db(DB);do{$H=connection()->store_result();if(is_object($H))print_select_result($H,$Bb);else
echo"<p class='message'>".lang_format(['Routine has been called, %d row affected.','Routine has been called, %d rows affected.'],$ma)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($Rh)print_select_result(connection()->query("SELECT ".implode(", ",$Rh)));}}if($rf){$Dl=driver()->routineTriggers($_GET["call"]);if($Dl){echo"<table>\n";foreach($Dl
as$J){$y=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($J["ns"]),ME);echo"<tr><th>".h($J["trigger"])."<td><a href='".h($y."table=".url_escape($J["table"])."#triggers")."'>".($J["ns"]!=$_GET["ns"]?"<b>".h($J["ns"])."</b>.":"").h($J["table"])."</a>"."<td class='hover'><a href='".h($y."trigger=".url_escape($J["table"])."&name=".url_escape($J["trigger"]))."'>".'Alter'."</a>\n";}echo"</table>\n";}}else{echo'<form method="post">
';if($Pe){echo"<table class='layout'>\n";foreach($Pe
as$v){$j=$wj["fields"][$v];$A=$j["field"];echo"<tr><th>".adminer()->fieldName($j);$Y=idx($_POST["fields"],$A);if($Y!=""){if($j["type"]=="set")$Y=implode(",",$Y);}input($j,$Y,idx($_POST["function"],$A,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>
';}echo'
',adminer()->commentValue($Aj,$wj['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$A=$_GET["name"];$J=$_POST;if($_POST&&!$i&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$J["source"]=array_filter($J["source"],'strlen');ksort($J["source"]);$cl=[];foreach($J["source"]as$v=>$X)$cl[$v]=$J["target"][$v];$J["target"]=$cl;}$Oa=false;if(JUSH=="sqlite")$H=recreate_table($a,$a,[],[],[" $A"=>($J["drop"]?"":" ".format_foreign_key($J))]);else{$Cb=$J["constraint"];$ta="ALTER TABLE ".table($a);$Gc="$ta DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($A?:'');if($J["drop"])$H=queries($Gc);else{$ha=" ADD".format_foreign_key($J,$Cb);if($A=="")$H=queries($ta.$ha);elseif(JUSH=="pgsql"||(JUSH=="sql"&&$Cb!=$A))$H=queries("$Gc,$ha");else{$Oa=support("transaction_ddl")&&driver()->begin();$H=($Oa||idx(foreign_keys($a),$A)?queries($Gc):true)&&queries($ta.$ha)&&(!$Oa||driver()->commit());}}}queries_redirect(ME."table=".url_escape($a),($J["drop"]?'Foreign key has been dropped.':($A!=""?'Foreign key has been altered.':'Foreign key has been created.')),$H);if($Oa)driver()->rollback();if(!$J["drop"])$i='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}$eh=false;if(!$_POST&&$A!=""){$Rd=foreign_keys($a);$J=idx($Rd,$A,[]);$eh=!$J;}$Qg=str_replace("{table}",$a,adminer()->namePattern("FOREIGN"));$Tg="^".str_replace('\{columns\}','.*',preg_quote($Qg))."$";page_header(($A!=""?'Alter foreign key':'Create foreign key'),$i,["table"=>$a],h($A!=""?$A:$a),$eh,doc_link(['sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",]));if($_POST){ksort($J["source"]);if($_POST["change"]||$_POST["change-js"])$J["target"]=[];else$J["source"][]="";}elseif($A!=""){$J["source"][]="";$J["constraint"]=$A;}else{$J["table"]=$a;$J["source"]=[""];$J["constraint"]=str_replace("{columns}","",$Qg);}echo'
<form method="post">
';$nk=array_keys(fields($a));if($J["db"]!="")connection()->select_db($J["db"]);$Nh=null;if($J["ns"]!=""){$Nh=get_schema();set_schema($J["ns"]);}$cj=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$cl=array_keys(fields(in_array($J["table"],$cj)?$J["table"]:reset($cj)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$cj,$J["table"],$b)."</label>\n";if(JUSH!="sqlite"){$Qj=($J["db"]!=""?$J["db"]:DB);$dc=[];foreach(adminer()->databases()as$g){if(!driver()->isSystem($g)||$g==$Qj||$g==DB)$dc[]=$g;}echo"<label>".'DB'.": ".html_select("db",$dc,$Qj,$b)."</label>";}if($J["db"]!="")connection()->select_db(DB);echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table',on('change','foreignName',$Tg,$Qg),'>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$uf=0;foreach($J["source"]as$v=>$X){echo"<tr>","<td>".html_select("source[".(+$v)."]",[-1=>""]+$nk,$X,($uf==count($J["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$v)."]",$cl,idx($J["target"],$v),"","label-target");$uf++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",[-1=>""]+explode("|",driver()->onActions),$J["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",[-1=>""]+explode("|",driver()->onActions),$J["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",['NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'],$J["deferrable"]):''),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($A!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$A)),'>
';if(JUSH!="sqlite"){print_fieldset("options",'Options',$J["constraint"]!=$A&&!preg_match("($Tg)",$J["constraint"]));echo"<label>".'Name'.": <input name='constraint' value='".h($J["constraint"])."' autocapitalize='off'></label>\n","</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$J=$_POST;$Oh="VIEW";if(JUSH=="pgsql"&&$a!=""){$P=table_status1($a);$Oh=strtoupper($P["Engine"]);}if($_POST&&!$i){$A=trim($J["name"]);$_a=" AS\n$J[select]";$z=ME."table=".url_escape($A);$ug='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$A&&JUSH!="sqlite"&&$U=="VIEW"&&$Oh=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($A).$_a,$z,$ug);else{$gl="adminer_".uniqid();drop_create("DROP $Oh ".table($a),"CREATE $U ".table($A).$_a,"DROP $U ".table($A),"CREATE $U ".table($gl).$_a,"DROP $U ".table($gl),($_POST["drop"]?substr(ME,0,-1):$z),'View has been dropped.',$ug,'View has been created.',$a,$A);}}$eh=false;if(!$_POST&&$a!=""){$J=view($a);$eh=!$J["select"];$J["name"]=$a;$J["materialized"]=($Oh!="VIEW");if(!$i)$i=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$i,["table"=>$a],h($a),$eh,doc_link(['sql'=>"create-view.html",]));echo'
<form method="post">
<p>Name: <input name="name" value="',h($J["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$J["materialized"],'Materialized view'):""),'<p>';textarea("select",$J["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$hf=["YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND"];$_k=["ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE"];$J=$_POST;if($_POST&&!$i){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($J["INTERVAL_FIELD"],$hf)&&isset($_k[$J["STATUS"]])){$Ej="\nON SCHEDULE ".($J["INTERVAL_VALUE"]?"EVERY ".q($J["INTERVAL_VALUE"])." $J[INTERVAL_FIELD]".($J["STARTS"]?" STARTS ".q($J["STARTS"]):"").($J["ENDS"]?" ENDS ".q($J["ENDS"]):""):"AT ".q($J["STARTS"]))." ON COMPLETION".($J["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$Ej.($aa!=$J["EVENT_NAME"]?"\nRENAME TO ".idf_escape($J["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($J["EVENT_NAME"]).$Ej)."\n".$_k[$J["STATUS"]]." COMMENT ".q($J["EVENT_COMMENT"]).rtrim(" DO\n$J[EVENT_DEFINITION]",";").";"));}}$eh=false;if(!$J&&$aa!=""){$K=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$eh=!$K;$J=reset($K);}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$i,"#events","",$eh,doc_link(['sql'=>"create-event.html"]));echo'
<form method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($J["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$J[EXECUTE_AT]$J[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($J["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($J["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$hf,$J["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$_k,$J["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($J["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$J["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$J["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$wj=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$J=$_POST;$J["fields"]=(array)$J["fields"];if($_POST&&!process_fields($J["fields"])&&!$i){foreach($J["fields"]as$v=>$j){if($j["field"]=="")unset($J["fields"][$v]);}$th=routine($_GET["procedure"],$wj);$rh=($th?routine_id($ba,$th):"");$Xg=routine_id($J["name"],$J);$Mb=create_routine($wj,$J);$z=substr(ME,0,-1);$ug='Routine has been altered.';if(!$_POST["drop"]&&$rh==$Xg&&connection()->flavor!="mysql")queries_redirect($z,$ug,queries(substr_replace($Mb,(JUSH=="mssql"?' OR ALTER':' OR REPLACE'),6,0)));else{$gl="adminer_".uniqid();drop_create("DROP $wj $rh",$Mb,"DROP $wj $Xg",create_routine($wj,["name"=>$gl]+$J),"DROP $wj ".routine_id($gl,$J),$z,'Routine has been dropped.',$ug,'Routine has been created.',$ba,$J["name"]);}}$eh=false;if(!$_POST&&$ba!=""){$J=routine($_GET["procedure"],$wj);$eh=!$J;$J["name"]=$ba;}$zj=strtolower($wj);page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$i,"#routines","",$eh,doc_link(['sql'=>"create-procedure.html",'mariadb'=>"create-$zj/",]));if(!$_POST&&$ba=="")$J["language"]="sql";$sb=(JUSH=="sql"?flat_collations():[]);$xj=routine_languages();echo($sb?"<datalist id='collations'>".optionlist($sb)."</datalist>":""),'
<form method="post" id="form">
<p>Name: <input name="name" value="',h($J["name"]),'" data-maxlength="64" autocapitalize="off">
',($xj?"<label>".'Language'.": ".html_select("language",array_keys($xj),$J["language"],on('change','routineLanguage',$xj))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($J["fields"],$sb,$wj);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$J["returns"],$sb,[],(JUSH=="pgsql"?["void","trigger"]:[]));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$J["definition"],20,80,($xj[$J["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$_j=routine_options($wj);if($_j){$Eh=false;foreach($_j
as$v=>$lm){$h=($lm?reset($lm):"");$J["options"][$v]=idx($J["options"],$v,$h);if($J["options"][$v]!=$h)$Eh=true;}print_fieldset("options",'Options',$Eh);echo"<table class='layout'>\n";foreach($_j
as$v=>$lm){$Bf="label-option-$v";$ol=str_replace("_"," ",$v);$M=[];foreach($lm
as$Y)$M[$Y]=(strpos($Y,"$ol ")===0?substr($Y,strlen($ol)+1):$Y);echo"<tr><th id='$Bf'>$ol<td>".($M?html_select("options[$v]",$M,$J["options"][$v],"",$Bf):"<input name='options[$v]' value='".h($J["options"][$v])."' aria-labelledby='$Bf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$A="$_GET[name]";$J=$_POST;if($J&&!$i){$z=ME."table=".url_escape($a);$xg='Check has been dropped.';$vg='Check has been altered.';$wg='Check has been created.';if(JUSH=="sqlite"&&!(min_version(3.53)&&($A==""||idx(driver()->checkConstraints($a),$A)!=$A))){$ha=($J["drop"]?"":($J["name"]!=""?"CONSTRAINT ".idf_escape($J["name"])." ":"")."CHECK ($J[clause])");queries_redirect($z,($J["drop"]?$xg:($A!=""?$vg:$wg)),recreate_table($a,$a,[],[],[],"",[],"$A",$ha));}else{$ta="ALTER TABLE ".table($a);$fb=" CHECK ($J[clause])";$gl="adminer_".uniqid();drop_create("$ta DROP CONSTRAINT ".idf_escape($A),"$ta ADD".($J["name"]!=""?" CONSTRAINT ".idf_escape($J["name"]):"").$fb,"$ta DROP CONSTRAINT ".idf_escape($J["name"]),"$ta ADD CONSTRAINT ".idf_escape($gl).$fb,"$ta DROP CONSTRAINT ".idf_escape($gl),$z,$xg,$vg,$wg,$A,$J["name"]);}}$eh=false;if(!$J){$ib=driver()->checkConstraints($a);$eh=($A!=""&&!$ib[$A]);$J=["name"=>($A==""?object_name("CHECK",$a,[]):($ib[$A]!=$A?$A:"")),"clause"=>$ib[$A]];}page_header(($A!=""?'Alter check':'Create check'),$i,["table"=>$a],h($A!=""?$A:$a),$eh,doc_link(['sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",]));echo'
<form method="post">
<p><label>Name: <input name="name" value="',h($J["name"]),'" data-maxlength="64" autocapitalize="off"></label>
<p>';textarea("clause",$J["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($A!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$A)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$A="$_GET[name]";$Cl=trigger_options();$J=trigger($A,$a);$eh=($A!=""&&!$J);$Qg=str_replace("{table}",$a,adminer()->namePattern("TRIGGER"));$J+=["Trigger"=>strtr($Qg,["{timing}"=>"b","{event}"=>"i","{columns}"=>"","{type}"=>"row"])];if($_POST){if(!$i&&in_array($_POST["Timing"],$Cl["Timing"])&&in_array($_POST["Event"],$Cl["Event"])&&in_array($_POST["Type"],$Cl["Type"])){$vh=" ON ".table($a);$Gc="DROP TRIGGER ".idf_escape($A).(JUSH=="pgsql"?$vh:"");$z=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($Gc,$z,'Trigger has been dropped.');else{if($A!="")queries($Gc);queries_redirect($z,($A!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($vh,$_POST)));if($A!="")queries(create_trigger($vh,$J+["Type"=>reset($Cl["Type"])]));}}$J=$_POST;}page_header(($A!=""?'Alter trigger':'Create trigger'),$i,["table"=>$a],h($A!=""?$A:$a),$eh,doc_link(['sql'=>"create-trigger.html",]));$Tg=strtr(preg_quote($Qg),['\{timing\}'=>'[abi]','\{event\}'=>'[iud]*','\{columns\}'=>'.*','\{type\}'=>'(row|statement)']);$Bl=on('change','triggerChange',"^$Tg$",$Qg);$kh=on('input','triggerChange',"^$Tg$",$Qg);echo'
<form method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$Cl["Timing"],$J["Timing"],$Bl),'<tr><th>Event<td>',html_select("Event",$Cl["Event"],$J["Event"],$Bl),(in_array("UPDATE OF",$Cl["Event"])?" <input name='Of' value='".h($J["Of"])."' class='hidden'$kh>":""),'<tr><th>Type<td>',html_select("Type",$Cl["Type"],$J["Type"],$Bl),'<tr><th>Name<td><input name="Trigger" value="',h($J["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$J["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($A!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$A)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($ee,array$Mi,$d,$vh){if(!$Mi)return
true;if($Mi==["ALL PRIVILEGES","GRANT OPTION"])return($ee=="GRANT"?queries("$ee ALL PRIVILEGES$vh WITH GRANT OPTION"):queries("$ee ALL PRIVILEGES$vh")&&queries("$ee GRANT OPTION$vh"));return
queries("$ee ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$Mi).$d).$vh);}$da=$_GET["user"];$Mi=[""=>["All privileges"=>""]];foreach(get_rows("SHOW PRIVILEGES")as$J){foreach(explode(",",($J["Privilege"]=="Grant option"?"":$J["Context"]))as$Gb)$Mi[$Gb=="File access on server"?"Server Admin":$Gb][$J["Privilege"]]=$J["Comment"];}unset($Mi["Server Admin"]["Usage"]);foreach($Mi["Tables"]as$v=>$X)unset($Mi["Databases"][$v]);$Wg=[];if($_POST){foreach($_POST["objects"]as$v=>$X)$Wg[$X]=(array)$Wg[$X]+idx($_POST["grants"],$v,[]);}$fe=[];$H=(isset($_GET["host"])?connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])):null);$eh=(isset($_GET["host"])&&!$H);if($H){while($J=$H->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$J[0],$_)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$_[1],$cg,PREG_SET_ORDER)){foreach($cg
as$X){if($X[1]!="USAGE")$fe["$_[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$J[0]))$fe["$_[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$i){$uh=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $uh",ME."privileges=",'User has been dropped.');else{$ah=q($_POST["user"])."@".q($_POST["host"]);$ji=$_POST["pass"];$Ob=false;$H=true;if($uh!=$ah){$Ob=queries("CREATE USER $ah IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($ji));$H=$Ob;}elseif($ji!="")$H=queries("SET PASSWORD FOR $ah = ".(min_version(8,99)||$_POST["hashed"]?q($ji):"PASSWORD(".q($ji).")"));if($H){$sj=[];foreach($Wg
as$ih=>$ee){if(isset($_GET["grant"]))$ee=array_filter($ee);$ee=array_keys($ee);if(isset($_GET["grant"]))$sj=array_diff(array_keys(array_filter($Wg[$ih],'strlen')),$ee);elseif($uh==$ah){$qh=array_keys((array)$fe[$ih]);$sj=array_diff($qh,$ee);$ee=array_diff($ee,$qh);unset($fe[$ih]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$ih,$_)&&(!grant("REVOKE",$sj,$_[2]," ON $_[1] FROM $ah")||!grant("GRANT",$ee,$_[2]," ON $_[1] TO $ah"))){$H=false;break;}}}if($H&&isset($_GET["host"])){if($uh!=$ah)queries("DROP USER $uh");elseif(!isset($_GET["grant"])){foreach($fe
as$ih=>$sj){if(preg_match('~^(.+)(\(.*\))?$~U',$ih,$_))grant("REVOKE",array_keys($sj),$_[2]," ON $_[1] FROM $ah");}}}if($H&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$H);if($Ob)connection()->query("DROP USER $ah");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$i,["privileges"=>['','Privileges']],"",$eh,doc_link(['sql'=>"grant.html",'mariadb'=>"grant"]));$J=$_POST;if($J)$fe=$Wg;else{$J=$_GET+["host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")];$fe[(DB==""||$fe?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=[];}echo'<form method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($J["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($J["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($J["pass"]),'" autocomplete="new-password">
',($J["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$J["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges';$q=0;foreach($fe
as$ih=>$ee){echo'<th>'.($ih!="*.*"?"<input name='objects[$q]' value='".h($ih)."' size='10' autocapitalize='off'>":input_hidden("objects[$q]","*.*")."*.*");$q++;}echo"<tbody>\n";foreach([""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',]as$Gb=>$nc){foreach((array)$Mi[$Gb]as$Li=>$wb){echo"<tr><td".($nc?">$nc<td":" colspan='2'").' lang="en" title="'.h($wb).'">'.h($Li);$q=0;foreach($fe
as$ih=>$ee){$A="'grants[$q][".h(strtoupper($Li))."]'";$Y=$ee[strtoupper($Li)];if($Gb=="Server Admin"&&$ih!=(isset($fe["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$A><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$A value='1'".($Y?" checked":"").($Li=="All privileges"?" id='grants-$q-all'":($Li=="Grant option"?"":on('click','grantsClick',"grants-$q-all"))).">","</label>";$q++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$i){$Af=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$Af++;}queries_redirect(ME."processlist=",lang_format(['%d process has been killed.','%d processes have been killed.'],$Af),$Af||!$_POST["kill"]);}}page_header('Process list',$i);echo'
<form method="post">
<div class="scrollable">
<table class="nowrap checkable odds"',on('click','tableClick').on('dblclick','tableClick'),'>
';$q=-1;foreach(adminer()->processList()as$q=>$J){if(!$q){echo"<thead><tr lang='en'>".(support("kill")?"<td class='hover'>":"");foreach($J
as$v=>$X)echo"<th>$v".doc_link(['sql'=>"show-processlist.html#processlist_".strtolower($v),]);echo"<tbody>\n";}echo"<tr>".(support("kill")?"<td class='hover'>".checkbox("kill[]",$J[JUSH=="sql"?"Id":"pid"],0):"");foreach($J
as$v=>$X)echo"<td>".($X!=""&&((JUSH=="sql"&&$v=="Info"&&preg_match("~Query|Killed~",$J["Command"]))||(JUSH=="pgsql"&&$v=="query")||(JUSH=="oracle"&&$v=="sql_text"))?"<code class='sql jush-".JUSH."' data-full='".h($X)."'>".shorten_utf8($X,100,"</code>").' <a href="'.h(($J["db"]!=""?preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($J["db"])."&":ME)."sql=".url_escape($X)).'">'.'Clone'.'</a>'.' '.copy_icon():h($X));echo"\n";}echo'</table>
</div>
<p>
',script("copyCode(qsl('table'));");if(support("kill"))echo
format_number($q+1)."/".sprintf('%d in total',max_connections()),"<p><input type='submit' value='".'Kill'."'>\n";echo
input_token(),'</form>
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$u=indexes($a);$k=fields($a);$Rd=column_foreign_keys($a);$ph=$S["Oid"];$uj=[];$d=[];$Kj=[];$Gh=[];$jl=null;foreach($k
as$v=>$j){$A=adminer()->fieldName($j);$Sg=html_entity_decode(strip_tags($A),ENT_QUOTES);if(isset($j["privileges"]["select"])&&$A!=""){$d[$v]=$Sg;if(is_shortable($j))$jl=adminer()->selectLengthProcess();}if(isset($j["privileges"]["where"])&&$A!="")$Kj[$v]=$Sg;if(isset($j["privileges"]["order"])&&$A!="")$Gh[$v]=$Sg;$uj+=$j["privileges"];}list($M,$p)=adminer()->selectColumnsProcess($d,$u);$M=array_unique($M);$p=array_unique($p);$of=count($p)<count($M);$Z=adminer()->selectSearchProcess($k,$u,$S);$C=adminer()->selectOrderProcess($k,$u);$x=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Ol=>$J){$_a=convert_field($k[key($J)]);$M=[$_a?:idf_escape(key($J))];$Z[]=where_check(bracket_escape($Ol,true),$k);$I=driver()->select($a,$M,$Z,$M);if($I)echo
first($I->fetch_row());}exit;}$Ii=$Ql=[];foreach($u
as$t){if($t["type"]=="PRIMARY"){$Ii=array_flip($t["columns"]);$Ql=($M?$Ii:[]);foreach($Ql
as$v=>$X){if(in_array(idf_escape($v),$M))unset($Ql[$v]);}break;}}if($ph&&!$Ii){$Ii=$Ql=[$ph=>0];$u[]=["type"=>"PRIMARY","columns"=>[$ph]];}if($_POST&&!$i){$wm=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$ib=[];foreach($_POST["check"]as$fb)$ib[]=where_check($fb,$k);$wm[]="((".implode(") OR (",$ib)."))";}$ym=$wm;$wm=($wm?"\nWHERE ".implode(" AND ",$wm):"");if($_POST["export"]){save_settings(["output"=>$_POST["output"],"format"=>$_POST["format"]],"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$Oj=($M?:["*"]);$Ib=convert_fields($d,$k,$M);if($Ib)$Oj[]=substr($Ib,2);$G="";if(is_array($_POST["check"])&&!$Ii){$Wd=implode(", ",$Oj)."\nFROM ".table($a);$ie=($p&&$of?"\nGROUP BY ".implode(", ",$p):"").($C?"\nORDER BY ".implode(", ",$C):"");$Ll=[];foreach($_POST["check"]as$X)$Ll[]="(SELECT".limit($Wd,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k).$ie,1).")";$G=implode(" UNION ALL ",$Ll);}adminer()->dumpData($a,"table",$G,$Oj,$ym,($of?$p:[]),$C);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$Rd)){if($_POST["save"]||$_POST["delete"]){$H=true;$ma=0;$Oa=false;$O=[];if(!$_POST["delete"]){foreach($k
as$A=>$X){$s=bracket_escape($A);if(isset($_POST["fields"][$s])||$_FILES["fields-$s"]){$X=process_input($k[$A]);if($X!==null&&($_POST["clone"]||$X!==false))$O[idf_escape($A)]=($X!==false?$X:idf_escape($A));}}}if($_POST["delete"]||$O){$G=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($O)).")\nSELECT ".implode(", ",$O)."\nFROM ".table($a):"");if($_POST["all"]||($Ii&&is_array($_POST["check"]))||$of){$H=($_POST["delete"]?driver()->delete($a,$wm):($_POST["clone"]?queries("INSERT $G$wm".driver()->insertReturning($a)):driver()->update($a,$O,$wm)));$ma=connection()->affected_rows;if(is_object($H))$ma+=$H->num_rows;}else{$Oa=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$vm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$k);$H=($_POST["delete"]?driver()->delete($a,$vm,1):($_POST["clone"]?queries("INSERT".limit1($a,$G,$vm)):driver()->update($a,$O,$vm,1)));if(!$H)break;$ma+=connection()->affected_rows;}if($Oa&&$H&&!driver()->commit())$H=false;}}$ug=lang_format(['%d item has been affected.','%d items have been affected.'],$ma);if($_POST["clone"]&&$H&&$ma==1){$If=last_id($H);if($If)$ug=sprintf('Item%s has been inserted.'," $If");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$ug,$H);if($Oa)driver()->rollback();if(!$_POST["delete"]){$_i=(array)$_POST["fields"];edit_form($a,array_intersect_key($k,$_i),$_i,!$_POST["clone"],$i);page_footer();exit;}}elseif(!$_POST["import"]){$H=true;$ma=0;$Oa=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$Ol=>$J){$O=[];foreach($J
as$v=>$X){$v=bracket_escape($v,true);$O[idf_escape($v)]=(preg_match('~char|text~',$k[$v]["type"])||$X!=""?adminer()->processInput($k[$v],$X):"NULL");}$H=driver()->update($a,$O," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($Ol,true),$k),($of||$Ii?0:1)," ");if(!$H)break;$ma+=connection()->affected_rows;}if($Oa)$H=$H&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(['%d item has been affected.','%d items have been affected.'],$ma),$H);if($Oa)driver()->rollback();}else{save_settings(["format"=>$_POST["separator"]],"adminer_import");$Ed=get_file("csv_file",true);if(!is_string($Ed))$i=upload_error($Ed);elseif(!preg_match('~~u',$Ed))$i='File must be in UTF-8 encoding.';else{$tb=array_keys($k);$Tj=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Sb=parse_csv($Ed,$Tj);$ma=count($Sb);driver()->begin();$K=[];foreach($Sb
as$v=>$lm){if(!$v&&!array_diff($lm,$tb)){$tb=$lm;$ma--;}else{$O=[];foreach($lm
as$q=>$pb)$O[idf_escape($tb[$q])]=($pb==""&&$k[$tb[$q]]["null"]?"NULL":q(csv_value($pb)));$K[]=$O;}}$H=(!$K||driver()->insertUpdate($a,$K,$Ii));if($H)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(['%d row has been imported.','%d rows have been imported.'],$ma),$H);driver()->rollback();}}}}$Rk=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $Rk",$i,[],"",(!$k&&support("table")),($k?doc_link([JUSH=>driver()->tableHelp($a,is_view($S))]):""));$O=null;if(isset($uj["insert"])||!support("table")){$O="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$_)?$_[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$O
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$O);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table.'."\n";else{echo"<form id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($M,$d);adminer()->selectSearchPrint($Z,$Kj,$u,$S);adminer()->selectOrderPrint($C,$Gh,$u);adminer()->selectLimitPrint($x);if($jl!==null)adminer()->selectLengthPrint($jl);adminer()->selectActionPrint($u);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],["","same-origin"])){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$D=$_GET["page"];$Ud=null;if($D=="last"){$Ud=get_val(count_rows($a,$Z,$of,$p));$D=floor(max(0,intval($Ud)-1)/$x);}$Nj=$M;$he=$p;if(!$Nj){$Nj[]="*";$Ib=convert_fields($d,$k,$M);if($Ib)$Nj[]=substr($Ib,2);}foreach($M
as$v=>$X){$j=$k[idf_unescape($X)];if($j&&($_a=convert_field($j)))$Nj[$v]="$_a AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$v=>$X){if(isset($Nj[$v])&&$X["fun"])$Nj[$v].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$of&&$Ql){foreach($Ql
as$v=>$X){$Nj[]=idf_escape($v);if($he)$he[]=idf_escape($v);}}$H=driver()->select($a,$Nj,$Z,$he,$C,$x,$D,true);if(!is_object($H))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$D)$H->seek($x*$D);$Tc=[];$K=[];while($J=$H->fetch_assoc()){if($D&&JUSH=="oracle")unset($J["RNUM"]);$K[]=$J;}$se=($x&&(support("cursor")?$_GET["next"]!="":count($K)>=$x));if(is_ajax()&&$se)header("X-Next-Page: ".pagination_href($D+1));if($_GET["modify"]&&$K){$lg=max_input_vars(count($K[0])+1,20);echo($lg&&count($K)>$lg?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form method='post' enctype='multipart/form-data'".on_upload_progress($Wl).">\n";if($_GET["page"]!="last"&&$x&&$p&&$of&&JUSH=="sql")$Ud=get_val(" SELECT FOUND_ROWS()");if(!$K)echo"<p class='message'>".'No rows.'."\n";else{$Ka=adminer()->backwardKeys($a,$Rk);$qj=[];reset($M);foreach($K[0]as$v=>$X){if(!isset($Ql[$v])){$X=idx($_GET["columns"],key($M))?:[];$qj[$v]=["fun"=>$X["fun"],"col"=>($M?$X["col"]:$v)];next($M);}}echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$p&&$M?"":"<td class='hover check sticky'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$Ug=[];$Zi=1;foreach($qj
as$v=>$X){$j=$k[$X["col"]];$A=($j?adminer()->fieldName($j,$Zi):($X["fun"]?($X["col"]!=""?h($X["col"]):"*"):h($v)));if($A!=""){$Zi++;$Ug[$v]=$A;$c=idf_escape($v);$Ge=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($v);$nc="&desc[0]=1";$kk=preg_replace('~ DESC( NULLS LAST)?$~','',$C[0]);$mk=($kk==$c||$kk==$v);echo"<th id='th[".h(bracket_escape($v))."]'".($mk?" aria-sort='".($kk==$C[0]?"ascending":"descending")."'":"").">";$ae=apply_sql_function(h($X["fun"]),$A);$lk=isset($j["privileges"]["order"])||$X["fun"];echo($lk?"<a href='".h($Ge.($mk&&$kk==$C[0]?$nc:''))."'>$ae</a>":$ae),doc_link([JUSH=>$j["doc"]]);$tg=($lk?"<a href='".h($Ge.$nc)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($j["privileges"]["where"]))$tg
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$v)."> =</a>";echo($tg?"<span class='column'>$tg</span>":"");}}$Pf=[];if($_GET["modify"]){foreach($K
as$J){foreach($J
as$v=>$X)$Pf[$v]=max($Pf[$v],min(40,utf8_length((string)$X)));}}$Be=[];$Ae=[];foreach((array)$_GET["where"]as$X){$X+=["col"=>"","op"=>"","val"=>""];$pb=$X["col"];$Jj=$X["val"];if(!is_array($Jj)&&($Jj!=""||preg_match('~NULL$~',$X["op"]))&&(!$X["op"]||in_array($X["op"],adminer()->operators($S)))){$Rf=strtr(preg_quote($Jj),["%"=>".*?","_"=>"."]);$pi=["LIKE %%"=>$Rf,"ILIKE %%"=>$Rf,"REGEXP"=>$Jj]+(JUSH=="pgsql"?["~"=>$Jj,"~*"=>$Jj]:[])+($pb!=""?[]:["="=>'^'.preg_quote($Jj).'\z',"IN"=>'^(?:'.implode("|",array_map('preg_quote',array_map('trim',explode(",",$Jj)))).')\z',"LIKE"=>"^$Rf\\z","ILIKE"=>"^$Rf\\z","FIND_IN_SET"=>'(?<=^|,)'.preg_quote($Jj).'(?=,|\z)',]);foreach(($pb!=""?[$pb=>$k[$pb]]:$k)as$A=>$j){if($pb!=""||is_searchable($j,$X)){$xh=$X["op"]?:(!preg_match('~'.text_type().'~',$j["type"])?"IN":(preg_match('~%~',$Jj)?"LIKE":"LIKE %%"));if(isset($pi[$xh])){$lb=preg_match('~^ILIKE|\*$~',$xh)||($xh!="~"&&preg_match('~^(sql|mssql|sqlite)$~',JUSH));$Be[$A][]="(?".($lb?"i":"").":$pi[$xh])";}elseif($xh=="IS NULL"&&$pb=="")$Ae[$A]=true;}}}}echo($Ka?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($K,$Rd)as$Og=>$J){$Nl=unique_array($K[$Og],$u);if(!$Nl){$Nl=[];foreach($K[$Og]as$v=>$X){if(!in_array(idx(idx($qj,$v,[]),"fun"),driver()->grouping))$Nl[$v]=$X;}}$Ol="";$q=0;foreach($Nl
as$v=>$X){$pj=idx($qj,$v,[]);if($M&&$pj&&!isset($k[$pj["col"]]))continue;$ae=idx($pj,"fun","");$pb=($ae?$pj["col"]:$v);$j=(array)$k[$pb];$nf=is_blob($j);if(!$ae&&strlen($X)>64&&driver()->md5(idf_escape($pb),$j)){$ae="md5";$X=md5($nf?(string)driver()->value($X,$j):$X);}if($ae){$Ol
.="&fun[$q]=".url_escape($ae)."&col[$q]=".url_escape($pb).($X!==null?"&val[$q]=".url_escape($X===false?"f":$X):"");$q++;}else$Ol
.="&".($X!==null?"where[".url_escape(bracket_escape($pb))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($pb));}echo"<tr>".(!$p&&$M?"":"<td class='hover check sticky'>".($of||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$Ol)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($Ol,1),in_array(substr($Ol,1),(array)$_POST["check"])));foreach($J
as$v=>$X){if(isset($Ug[$v])){$ae=$qj[$v]["fun"];$pb=$qj[$v]["col"];$j=(array)$k[$v];if($X!=""&&(!isset($Tc[$v])||$Tc[$v]!=""))$Tc[$v]=(is_mail($X)?$Ug[$v]:"");$y="";if(is_blob($j)&&$X!="")$y=ME.'download='.url_escape($a).'&field='.url_escape($v).$Ol;if(!$y&&$X!==null){foreach((array)$Rd[$v]as$m){if(count($Rd[$v])==1||end($m["source"])==$v){$y="";foreach($m["source"]as$q=>$nk)$y
.=where_link($q,$m["target"][$q],$K[$Og][$nk]);$y=($m["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($m["db"]),ME):ME).'select='.url_escape($m["table"]).$y;if($m["ns"]!="")$y=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($m["ns"]),$y);if(count($m["source"])==1)break;}}}if($ae=="count"&&$pb==""){$y=ME."select=".url_escape($a);$q=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$Nl))$y
.=where_link($q++,$W["col"],$W["val"],$W["op"]);}foreach($Nl
as$yf=>$W){if(idx(idx($qj,$yf,[]),"fun")){$y="";break;}$y
.=where_link($q++,$yf,$W);}}$He=select_value($X,$y,$j,$jl,($ae?[]:idx($Be,$v,[])));if($X===null&&!$ae&&isset($Ae[$v]))$He="<mark>$He</mark>";$s=bracket_escape($Ol);$r=h("val[$s][".bracket_escape($v)."]");$Bi=idx(idx($_POST["val"],$s),bracket_escape($v));$Tl=idx($j["privileges"],"update")&&!is_identity_always($j);$Oc=$K[$Og][$v];$Pc=!is_array($J[$v])&&!is_blob($j)&&is_utf8($Oc)&&!$ae&&!$j["generated"]&&$Tl;$U=($ae=="min"||$ae=="max"?$k[$pb]["type"]:$j["type"]);$il=preg_match('~text|json|lob~',$U);$pf=preg_match(number_type(),$U)||preg_match('~^(avg|ceil|char_length|count|count distinct|floor|len|length|round|sum|time_to_sec)$~',$ae);echo"<td id='$r'".($pf&&($X===null||is_numeric(strip_tags($He))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Pc&&$Oc!==null)||$Bi!==null){$ne=h($Bi!==null?$Bi:$Oc);echo">".($il?"<textarea name='$r' cols='30' rows='".(substr_count($Oc,"\n")+1)."'>\n$ne</textarea>":"<input name='$r' value='$ne' size='$Pf[$v]'>");}else{$Zf=strpos($He,"<i>…</i>");$Nc=adminer()->editVal($Oc,$j);echo($Tl?" data-text='".($Zf?2:($il?1:0))."'".($Pc?"":" data-warning='".'Use the edit link to modify this value.'."'").($Pc&&!$Zf&&html_entity_decode(strip_tags($He),ENT_QUOTES,"UTF-8")!==$Nc?" data-value='".h($Nc)."'":""):"").">$He";}}}if($Ka)echo"<td>";adminer()->backwardKeysPrint($Ka,$K[$Og]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$la=get_settings("adminer_import");if($K||$D||$se){$ld=true;if($_GET["page"]!="last"){if(!$x||(count($K)<$x&&($K||!$D)))$Ud=($D?$D*$x:0)+count($K);elseif(JUSH!="sql"||!$of){$Ud=($of?null:found_rows($S,$Z));$ld=!driver()->hasEstimatedRows();if($Ud===null||(!$ld&&$Ud<max(1e4,2*($D+1)*$x))){$Ud=first(slow_query(count_rows($a,$Z,$of,$p)));$ld=true;}}}if(!support("cursor"))$se=(($Ud===false?count($K)+1:$Ud-$D*$x)>$x);$Uh=($x&&($se||$D));if($Uh)echo($se?'<p><a href="'.h(pagination_href($D+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($Uh){$jg=($Ud===false?$D+($K?(count($K)>=$x?2:1):0):floor(($Ud-1)/$x));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$D).($D>5?" …":"");for($q=max(1,$D-4);$q<min($jg,$D+5);$q++)echo
pagination($q,$D);if($jg>0)echo($D+5<$jg?" …":""),($ld&&$Ud!==false?pagination($jg,$D):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$jg'>".'last'."</a>");}else
echo
pagination(0,$D).($D>1?" …":""),($D?pagination($D,$D):""),($se?pagination($D+1,$D)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$vc=($ld?"":"~ ").$Ud;$Bf=($Ud!==false?($ld?"":"~ ").lang_format(['%d row','%d rows'],$Ud):"");echo
checkbox("all",1,0,$Bf,on('click','countRows',$vc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]||$_POST["val"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Sd=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]||($c["col"]!=""&&!isset($k[$c["col"]]))){unset($Sd['sql']);break;}}if($Sd){print_fieldset("export",'Export'." <span id='selected2'></span>");$Sh=adminer()->dumpOutput();echo($Sh?html_select("output",$Sh,$la["output"])." ":""),html_select("format",$Sd,$la["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Tc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($Wl?input_hidden(ini_get("session.upload_progress.name"),$Wl):""),file_input(" name='csv_file'"," ".html_select("separator",["csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"],$la["format"])." <input type='submit' name='import' value='".'Import'."'>".($Wl?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$p&&$M?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$P=isset($_GET["status"]);page_header($P?'Status':'Variables');$mm=($P?adminer()->showStatus():adminer()->showVariables());if(!$mm)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($mm
as$J){echo"<tr>";$v=array_shift($J);echo"<th><code class='jush-".JUSH.($P?"status":"set")."'>".h($v)."</code>";foreach($J
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$Hk=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];foreach(table_status()as$A=>$S){json_row("Comment-$A",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(["Engine","Collation"]as$v)json_row("$v-$A",h($S[$v]));foreach(array_keys($Hk+["Auto_increment"=>0,"Rows"=>0])as$v){if(array_key_exists($v,$S))json_row("$v-$A",format_status($S,$v));if($S[$v]!=""&&isset($Hk[$v]))$Hk[$v]+=($S["Engine"]!="InnoDB"||$v!="Data_free"?$S[$v]:0);}}}if(function_exists('Adminer\db_status'))$Hk=db_status();foreach($Hk
as$v=>$X)json_row("sum-$v",adminer()->formatSizeValue($X));json_row("");}else{foreach(count_tables(adminer()->databases(false))as$g=>$X){json_row("tables-$g",format_number($X));json_row("size-$g",db_size($g));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$qg=ME.(isset($_GET["select"])?"select=&":"");$al=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($al&&!$i&&!$_POST["search"]){$H=true;$ug="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$H=truncate_tables($_POST["tables"]);$ug='Tables have been truncated.';}elseif($_POST["move"]){$H=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$ug='Tables have been moved.';}elseif($_POST["copy"]){$H=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$ug='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$H=drop_views($_POST["views"]);if($H&&$_POST["tables"])$H=drop_tables($_POST["tables"]);$ug='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$J)$ug
.="<b>".h($R)."</b>: ".h($J["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$J)$ug
.="<b>".h($R)."</b>: ".h($J["MessageText"])."<br>";}}elseif(JUSH!="sql"){$H=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$ug='Tables have been optimized.';}elseif(!$_POST["tables"])$ug='No tables.';elseif($H=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($J=$H->fetch_assoc())$ug
.="<b>".h($J["Table"])."</b>: ".h($J["Msg_text"])."<br>";}queries_redirect(relative_uri(),$ug,$H);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$i,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$C=$_GET["order"];$Xd=($C||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$Zk=($Xd?table_status():tables_list());$Id=$_GET["filter"];if($Id!=""){foreach($Zk
as$R=>$P){if(stripos("$R",$Id)===false)unset($Zk[$R]);}}if(!$Zk)echo"<p class='message'>".'No tables.'."\n";else{echo"<form method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"));$G=($_POST?$_POST["query"]:$_GET["query"]);echo" <input type='search' name='query' value='".h($G!=""?$G:$Id)."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$i&&(!$_POST||$_POST["search"])&&$G!=""){$_GET["where"][0]["op"]=idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%");search_tables($G);}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr>','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th class="sticky"'.(!$C&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($qg,0,-1)).'">'.'Table'.'</a>';$d=["Engine"=>['Engine'.doc_link(['sql'=>'storage-engines.html'])]];$sb=collations();if($sb)$d["Collation"]=['Collation'.doc_link(['sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/'])];if(function_exists('Adminer\alter_table'))$d["Data_length"]=['Data Length'.doc_link(['sql'=>'show-table-status.html',]),"create",'Alter table',];if(support("indexes"))$d["Index_length"]=['Index Length'.doc_link(['sql'=>'show-table-status.html',]),"indexes",'Alter indexes',];$d["Data_free"]=['Data Free'.doc_link(['sql'=>'show-table-status.html']),"edit",'New item'];if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=['Auto Increment'.doc_link(['sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/']),"auto_increment=1&create",'Alter table',];$d["Rows"]=['Rows'.doc_link(['sql'=>'show-table-status.html',]),"select",'Select data',];if(support("comment"))$d["Comment"]=['Comment'.doc_link(['sql'=>'show-table-status.html',]),];$Aa=['Engine','Collation','Comment'];foreach($d
as$v=>$c)echo"<th".($C==$v?" aria-sort='".(in_array($v,$Aa)?"ascending":"descending")."'":"")."><a href='".h($qg)."order=$v'>$c[0]</a>";echo"<tbody>\n";if($C){uasort($Zk,function($fa,$Ha)use($C,$Aa){$I=($fa[$C]<$Ha[$C]?-1:($fa[$C]>$Ha[$C]?1:0));return(in_array($C,$Aa)?$I:-$I);});}$T=0;$Hk=["Data_length"=>0,"Index_length"=>0,"Data_free"=>0];foreach($Zk
as$A=>$P){$pm=($Xd?is_view($P):$P!==null&&!preg_match('~table|sequence~i',$P));$P=($Xd?$P:['Engine'=>$P]);$r=h("Table-".$A);echo'<tr><td class="hover">'.checkbox(($pm?"views[]":"tables[]"),$A,in_array("$A",$al,true),"","","",$r),'<th class="sticky">'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($A)."' title='".'Show structure'."' id='$r'>".h($A).'</a>':h($A));if($pm&&!preg_match('~materialized~i',$P['Engine'])){$ol='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($A)."' title='".'Alter view'."'>$ol</a>":$ol),"<td align='right'><a href='".h(ME)."select=".url_escape($A)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($P['Comment']);}else{if($Xd){foreach(array_keys($Hk)as$v)$Hk[$v]+=($P["Engine"]!="InnoDB"||$v!="Data_free"?idx($P,$v):0);}foreach($d
as$v=>$c){$r=" id='$v-".h($A)."'";echo($c[1]?"<td align='right'>".($v=="Index_length"&&$P["invalid_index"]?"<span class='error'>INVALID</span> ":"")."<a href='".h(ME."$c[1]=").url_escape($A)."'$r title='$c[2]'>".format_status($P,$v)."</a>":"<td$r>".h(idx($P,$v,'?')).($v=="Comment"&&$P["Error"]?" <span class='error'>".h($P["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th class='sticky'>".sprintf('%d in total',count($Zk)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),($sb?"<td>".h(db_collation(DB,$sb)):'');if($Xd&&function_exists('Adminer\db_status'))$Hk=db_status();foreach($Hk
as$v=>$Gk)echo($d[$v]?"<td align='right' id='sum-$v'>".($Xd?adminer()->formatSizeValue($Gk):""):"");echo"\n","</table>\n",($Xd?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$im="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$Bh="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$Ji=(JUSH=="sqlite"?$im."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$im.$Bh:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$Bh."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($Ji?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$Ji\n</div></fieldset>\n":"");$f=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($f)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$g=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($f?html_select("target",$f,$g):'<input name="target" value="'.h($g).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$Bj=routines();if($Bj){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<th>'.'Type'.'<th>'.'Return type'."<td class='hover'><tbody>\n";foreach($Bj
as$J){$A=($J["SPECIFIC_NAME"]==$J["ROUTINE_NAME"]?"":"&name=".url_escape($J["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($J["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($J["SPECIFIC_NAME"]).$A).'"'.' title="'.($J["DTD_IDENTIFIER"]=="trigger"?'Triggers':'Call').'">'.h($J["ROUTINE_NAME"]).'</a>','<td>'.h($J["ROUTINE_TYPE"]),'<td>'.h($J["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($J["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($J["SPECIFIC_NAME"]).$A).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$K=get_rows("SHOW EVENTS");if($K){echo"<table>\n","<thead><tr><th>".'Name'."<th>".'Schedule'."<th>".'Start'."<th>".'End'."<td class='hover'><tbody>\n";foreach($K
as$J)echo"<tr>","<th>".h($J["Name"]),"<td>".($J["Execute at"]?'At given time'."<td>".h($J["Execute at"]):'Every'." ".h($J["Interval value"])." ".h($J["Interval field"])."<td>".h($J["Starts"])),"<td>".h($J["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($J["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$id=get_val("SELECT @@event_scheduler");if($id&&$id!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($id)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();