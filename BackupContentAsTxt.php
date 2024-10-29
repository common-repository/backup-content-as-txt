<?php

/* 
 * Plugin Name:   Backup Content As Txt
 * Version:       1.4
 * Plugin URI:    http://www.hotyear.com/backup-content-as-txt/
 * Description:  Backup Content As Txt
 * Author:        Jesse
 * Author URI:    http://www.hotyear.com/backup-content-as-txt/
 */

require(dirname(__FILE__) . "/zip.class.php");

class BackupContentAsTxt {

	var $zipfile;

	/*0 means default output format, 1 means default ouput format with date in second line*/
	var $option=0;	

	
function BackupContentAsTxt(){

	add_action('admin_menu', array(&$this,'backupcontentastxt_menu_setup'));
	$this->zipfile = new zipfile(); // Create an object

}

function backupcontentastxt_menu_setup() {
   add_options_page('Backup Content As Txt Settings', 'Backup Content As Txt', 10, __FILE__, array(&$this,'backupcontentastxt_menu'));

   
 if ($_POST["bcatsubmit"]) {

	//0 means backup all posts
	$categories = ($_POST['categories']?$_POST['categories']:0);
	$this->build($_POST['type'],$categories);
   }
 
}

function build($type,$categories){
        
	@set_time_limit(0);
	global $wpdb;
	$this->option=($_POST['format']=='date'?1:0);
	if($categories != 0 && $type != 'page' ){
		
		foreach($categories as $category ){
			$tid=get_category($category)->term_taxonomy_id;
			$sql="SELECT post_title,post_content,post_date FROM $wpdb->posts p, $wpdb->term_relationships r WHERE r.object_id = p.id AND r.term_taxonomy_id = $tid AND p.post_type = 'post'";
			$this->process($sql,get_cat_name($category).'/');
		}
		
	}else{
		$sql="select post_title,post_content,post_date from $wpdb->posts where post_type='$type'";
	
		$this->process($sql);
	}

	
	$zipname=date('Ymd').'_'.rand(0,999).'.zip';
	// Allow user to download file
	header("Content-Type: application/zip"); 
	header("Content-disposition: attachment;filename=\"$zipname\"");
	
	echo $this->zipfile->zipped_file(); 


}
function process($sql,$prefix=''){

	global $wpdb;
	
	$data=$wpdb->get_results($sql,ARRAY_A);
	
	
	foreach($data as $s){
			$filename=$s['post_title'].".txt";
			if($this->option == 1)
				$content=$s['post_title']."\n".$s['post_date']."\n".$s['post_content'];
			else			
				$content=$s['post_title']."\n".$s['post_content'];
			//$content=preg_replace("@<br />@","\n",$content);
			$this->zipfile->create_file($content, $prefix.$filename); 
	}
	
	
	
	

	

}




function backupcontentastxt_menu() {
 
   ?>
   <div class="wrap">

      <h2>Backup Content As Txt</h2>

    
      <form method="post" action="">
     
	<p>
       Output format:<br /><input type="radio" name="format" value="default" checked>Default:<span id="promt" style="color:red;"><br />1st line[title]<br />the rest:[content]</span><br /><br />
	            <input type="radio" name="format" value="date"  >Date<span id="promt" style="color:red;"><br />1st line[title]<br />2nd [date]<br />the rest:[content]</span><br />
		
      </p>
      <script language="javascript">  
  function   a(s)  
  {	
	if(s=='page'){

        	document.getElementById('categories').style.display='none';
       
	}else{
		
		 document.getElementById('categories').style.display='';
	}
	

  }  
  </script>
      <p>
       Type:<input type="radio" name="type" value="post" onclick="a(this.value);" checked>Post
	    <input type="radio" name="type" value="page" onclick="a(this.value);" >Page<br />
		
      </p>
	
	<div id="categories">
	<?php
	
	$categories = get_all_category_ids();
	foreach ($categories as $categoryid){

		echo '<input type="checkbox" value="'.$categoryid.'" name="categories[]">'. get_cat_name($categoryid) .'<br />';
	}
		
?>

   </div>
     
      <p><input type="submit" name="bcatsubmit" class="button" value="Backup" /></p>
     
  </form>
   </div>
   <?php
}


}
$backupcontentastxt = & new BackupContentAsTxt();




?>
