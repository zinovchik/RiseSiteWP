<?php
/**
 * @package RiseSiteWP
 * @version 1.0
 */
/*
Plugin Name: RiseSiteWP
Plugin URI: https://wordpress.org/plugins/RiseSiteWP/
Description: This is a plugin for rise post from WebArchive.
Author: Maxim Zinovchik
Version: 1.0
*/


//**********************************************
// Функция добавляет пункты меню в админку
function rs_add_menu() {
    add_menu_page('Rise Post', 'Rise Post', 8, 'rs_rise_post', 'rs_rise_post');
    add_submenu_page('rs_rise_post', 'Plagin Settings', 'Plagin Settings', 8, 'rs_settings', 'rs_settings');
}
add_action('admin_menu', 'rs_add_menu');


//**********************************
// Страница востановления постов
function rs_rise_post() {
	$rs_domen_name =  get_option('rs_domen_name');  

	?>
	<h2>Востановление постов сайта: <u><b><?=$rs_domen_name?></b></u></h2>
	<style type='text/css'>
		#wpbody-content td{
		    border: 1px solid #ccc;
		    padding: 5px 10px;
		    text-align: center;
		 }
	    .rs_error {
	       color:red;
	    }
	       
	    .rs_ok {
	       color:green;
	    }
	</style>
	<?php
	if (isset($_POST['rs_scan'])) { 

		//**********************************************************
		//АНАЛИЗ ДОСТУПНЫХ ВЕРСИЙ СТРАНИЦЫ В ВЕБ АРХИВЕ
		
		$list_of_pages = file_get_contents("http://web.archive.org/cdx/search/cdx?url=".$_POST['rs_url_page']."&output=json&limit=500");
		$list_of_pages=json_decode($list_of_pages,true); 
		if($list_of_pages) {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function(){
		    jQuery("#rs_list tr:not('#rs_list tr:first , #rs_list tr:last')").click(function(){ 
		        jQuery(this).find('input').prop("checked", true);
		        jQuery("#rs_list td").removeAttr('style');
		        jQuery(this).find('td').css({"backgroundColor" : "#D6D4D4"});
		       
		    });
		    jQuery("#rs_rise_form").click();
		});
		 </script>	
			<form method="post">
		        
		        <h4>URL: <?=$_POST['rs_url_page']?></h4>
			<table id='rs_list' cellspacing='0' style="margin: 0px 50px;">
				<tr>
					<td><b>№</b></td>	
					<td><b>Дата</b></td>
					<td><b>Статус</b></td>
					<td colspan="2"><b>Действия</b></td>	
				</tr>
					
				<?php
				$i=1;		
				array_shift($list_of_pages);
				rsort($list_of_pages);
							 
				$rs_checked=0;			 
				foreach ($list_of_pages as $one_page) { ?> 
				<tr>
					<?php if(!$rs_checked && ($one_page[4]=='200')) {$rs_tmp_style=' style="background-color: #d6d4d4;"';}?>
					<td<?=$rs_tmp_style?>><?php echo $i++; ?></td>
					<td<?=$rs_tmp_style?>><?php echo substr($one_page[1],6,2).'.'.substr($one_page[1],4,2).'.'.substr($one_page[1],0,4); ?></td>
					<td<?=$rs_tmp_style?>><?php echo $one_page[4]; ?></td>
					<td<?=$rs_tmp_style?>><a href="http://web.archive.org/web/<?php echo $one_page[1]; ?>/<?php echo $one_page[2]; ?>" target="_blank">Открыть</a></td>	
					<td<?=$rs_tmp_style?>><input type="radio" name="rs_version_date" value="<?=$one_page[1]?>" <?php if(!$rs_checked && ($one_page[4]=='200')) {echo "checked='checked'"; $rs_checked++;} ?> /></td>	
					<?php $rs_tmp_style=''; ?>
				</tr>	
				<?php } ?>
				<tr>
					<td colspan="5">
						<div class="submit">
							<input name="rs_url_page" type="hidden" value="<?=$_POST['rs_url_page']?>" />
					         	<input name="rs_rise"  id="rs_rise_form" type="submit" class="button-primary" value="<?php echo __('Read Post'); ?>" />
					        </div>
					</td>
				</tr>
			</table>
		    </form>
		<?php } else { 
			echo "<br><span class='rs_error'>* В Веб Архиве нет данных о текущей странице!</span>";
		} 
		
		//******************************************

	} elseif (isset($_POST['rs_rise'])) { 
		//ЧТЕНИЕ ИЗ ВЕБ АРХИВА СТРАНИЦЫ И ЕЕ ПАРСИНГ
		// подключение библиотеки
		include_once('simple_html_dom.php');
		global $wpdb;
		// $html - весь контент страницы узятой из веб архива
		// $rs_title - заголовок поста, потом адрес (slug) поста
		// rs_content - содержимое поста
		// rs_content_link - переменная для ссылок в посте
		// $rs_message - текст сообщения
		// $query - запрос к базе данных
		// $rs_i -  счетчик в цикле
		// $rs_tmp - временная переменная
		
		$rs_domen_name=get_option('rs_domen_name');
		$rs_get_title=get_option('rs_get_title');
		$rs_title_path=get_option('rs_title_path');
		$rs_get_slug=get_option('rs_get_slug');
		$rs_slug_path=get_option('rs_slug_path');
		$rs_get_date=get_option('rs_get_date');
		$rs_date_path=get_option('rs_date_path');
		$rs_get_content=get_option('rs_get_content');
		$rs_content_path=get_option('rs_content_path');
		$rs_get_category=get_option('rs_get_category');
		$rs_category_path=get_option('rs_category_path');
		
		$html = file_get_html("http://web.archive.org/web/$_POST[rs_version_date]/$_POST[rs_url_page]");
		
		echo "<form method='post'>";
		
		$rs_title = $html->find($rs_title_path, 0);
		echo "<label>Title <input name='rs_title' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_title->plaintext' /></label>";
		
		//$rs_slug = $html->find($rs_slug_path, 0);
		//$rs_slug = explode("/", $rs_slug->href);
		//$rs_slug = array_pop($rs_slug);
		//$rs_slug = str_replace(".html", '', $rs_slug);
		
		//$rs_slug = $html->find($rs_slug_path, 0);
		$rs_slug = explode("/", $_POST[rs_url_page]);
		$rs_slug = array_pop($rs_slug);
		$rs_slug = str_replace($rs_slug_path, '', $rs_slug);
		
		$query = "SELECT count(*) as c FROM `wp_posts` WHERE `post_status` = 'publish' AND `post_name` = '$rs_slug' AND `post_type` = 'post'"; 
		$rs_message = ($wpdb->get_var($wpdb->prepare($query))) ? "<span class='rs_error'>(Текущий адрес (slug) существует на сайте!!!)</span>" : '';
		
		echo "<label>Slug $rs_message<input name='rs_slug' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_slug' /></label>";
		
		$rs_date = $html->find($rs_date_path, 0);
		$rs_date = date("Y-m-d H:i:s",strtotime ($rs_date->plaintext));
		echo "<label>Date <input name='rs_date' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_date' /></label>";
		
		foreach($html->find($rs_category_path) as $e) 
		{$e1[]=$e->plaintext;}
		
		$e='';
		$args = array(
		  'hide_empty'=> 0,	
		  'type'=> 'post'
		  );
		$categories = get_categories($args);
		  foreach($categories as $category) { 
		  	$e[$category->term_id] = $category->name; 
		    }
		
		
		foreach($e1 as $value) { 
		  	
		    if (in_array($value, $e)) {
		    	 $e = array_flip($e);
		    	 $e2[]=$e[$value];
		    	 $e = array_flip($e);
		     } else {
		         $my_cat = array('cat_name' => $value);
		         // Create the category
		         echo '<br><span class="rs_ok">* create new category id = ';
		         echo $e2[] = wp_insert_category($my_cat);
		         echo ', Name = '.$value.'</span><br>';
		     }
		    }
		$e2 = implode(',', $e2);
		echo "<label>Category id <input name='rs_category' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$e2' /></label>";
		
		$rs_content = $html->find($rs_content_path, 0);

		foreach($rs_content->find('h1.post-title') as $e3)
		{$e3->outertext = '';
			echo '<span class="rs_error">* Удален заголовок!!!</span><br>';}

		foreach($rs_content->find('div.meta') as $e3)
		{$e3->outertext = '';
			echo '<span class="rs_error">* Удален верхний блок!!!</span><br>';}

		
		foreach($rs_content->find('textarea') as $e3)
		    {$e3->outertext = '';
		     echo '<span class="rs_error">* Удалена textarea!!!</span><br>';}


		
		//--поиск и удаление битых ссылок (анкор остается)
		$rs_i = 0;
		foreach($rs_content->find('a') as $rs_content_link) {
			$rs_i++;
			$rs_tmp = $rs_content_link->href;
			
			// убираем из ссылки код веб архива
			$rs_tmp = str_replace("/web/$_POST[rs_version_date]/", '', $rs_tmp);
			
			//Приведем все ссылки к стандартному виду, поставив всем в начало протокол http
			if (strpos($rs_tmp,"http:") === FALSE){ echo $rs_tmp="http://".$rs_tmp; }
		
		 	// если ссылка не рабочая то удаляем ее и оставляем анкор
		 	$rs_message = rs_open_url($rs_tmp);
		 	if ($rs_message == '200') {
		 		$rs_content_link->href = $rs_tmp;
		 	} 
		 	elseif ($rs_message == '301') {
		 		echo "<br><span class='rs_ok'> * в коде возможно не рабочая ссылка (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
		 		//$rs_content_link->href = $rs_tmp;
		 		$rs_content_link->outertext = $rs_content_link->innertext;
		 	} else
		 	{
		 		echo "<br><span class='rs_ok'> * не рабочая ссылка удалена (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
		 		$rs_content_link->outertext = $rs_content_link->innertext;
		 	}
		}
		echo $rs_i ? "<br><span class='rs_ok'> * ссылки на странице исправлены (удалено $rs_i: /web/$_POST[rs_version_date]/)</span>" : '';
		
		//--поиск изображений и исправление ссылок
		$rs_i = 0;
		foreach($rs_content->find('img') as $rs_content_img) {
			$rs_i++;
			$rs_tmp = $rs_content_img->src;
			
			// убираем из ссылки код веб архива
			$rs_tmp = str_replace("/web/$_POST[rs_version_date]im_/", '', $rs_tmp);
			
			//Приведем все ссылки к стандартному виду, поставив всем в начало протокол http
			//if (strpos($rs_tmp,"http:") === FALSE){ echo $rs_tmp="http://".$rs_tmp; }
			
			$rs_message = rs_open_url($rs_tmp);
			if ($rs_message != '200') {
		 		
		 		echo "<br><span class='rs_error'> * картинка удалена (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
		 		//$rs_content_img->outertext = '';
		 		$rs_tmp_array[] = $rs_content_img->outertext;
		 	} 
			
			$rs_content_img->src = $rs_tmp;
			//echo "<br>".rs_open_url($rs_tmp)." - $rs_tmp";
			
			
		 	
		 	
		}
		echo $rs_i ? "<br><span class='rs_ok'> * адреса картинок исправлены (удалено $rs_i: /web/$_POST[rs_version_date]im_/)</span>" : '';
		//print_r($rs_tmp_array);
		
		$html = $rs_content->innertext;
		
		foreach($rs_tmp_array as $rs_tmp) {
		
			$html = str_replace($rs_tmp, '', $html);
			echo '*';
			
		}
		
		
					//--поиск и исправление ссылок
		//rise_links("/web/$_POST[rs_version_date]/", "ссылки на странице исправлены", $html);			
					
					//--поиск и исправление адресов картинок
		rise_links("/web/$_POST[rs_version_date]im_/", "адреса картинок исправлены", $html);			
				
					//--поиск и исправление адресов js файлов
		rise_links("/web/$_POST[rs_version_date]js_/", "адреса js файлов исправлены", $html);			
					
					//--поиск и исправление адресов iframe тегов
		rise_links("/web/$_POST[rs_version_date]if_/", "адреса iframe тегов исправлены", $html);
					
					//--поиск и исправление адресов frame тегов
		rise_links("/web/$_POST[rs_version_date]fw_/", "адреса frame тегов исправлены", $html);
					
					//--поиск и исправление адресов css файлов
		rise_links("/web/$_POST[rs_version_date]cs_/", "адреса css файлов исправлены", $html);
					
					//--поиск и исправление адресов тега object
		rise_links("/web/$_POST[rs_version_date]oe_/", "адреса тега object исправлены", $html);
		
		$tmp_strrpos = strrpos($html, 'web/');
		echo $tmp_strrpos ? "<br><span class='rs_error'>* In post is set web archive code!!!</span>" : '';
		
		
		echo "<textarea  name='rs_content' style='display: block;height: 400px;margin: 10px 0;width: 99%; border:1px solid #777;'>".trim($html)."</textarea>";
		
		echo '<div class="submit">
		          <input name="rs_save" type="submit" class="button-primary" value="Save Page" />
		      </div>
		</form>';
		
		
		
		//******************************************

	} elseif (isset($_POST['rs_save'])) {
		// СОХРАНЕНИЕ ПОСТА ИЛИ СТРАНИЦЫ

        $np = array(
            'slug' => $_POST[rs_slug],
            'title' => $_POST[rs_title],
            'content' => $_POST[rs_content],
            'post_date' => $_POST[rs_date], //Дата создания поста.
	    'post_date_gmt' => $_POST[rs_date], //Дата создания поста по Гринвичу.
	    'post_category' => explode(",", $_POST[rs_category]) //Добавление ID категорий.
        );
        $rs_id = wp_insert_post( array(
                'post_title' => $np['title'],
                'post_type'    => 'post', // тип записи
                'post_name'     => $np['slug'], // URL, будут совпадения? WordPress сам все испраит.
                'comment_status' => 'closed', // обсждение закрыть
                'ping_status' => 'closed', // пинги запретить
                'post_content' => $np['content'],
                'post_status' => 'publish', // опубликовать
                'post_author' => 1, // кто будет автором
                'menu_order' => 0, // положение пункта в меню
                'post_date' => $np['post_date'], //Дата создания поста.
  		'post_date_gmt' => $np['post_date_gmt'], //Дата создания поста по Гринвичу.
  		'post_category' => $np['post_category'] //Добавление ID категорий.
            )); 
            
            if ($rs_id) {wp_redirect(get_site_url()."/wp-admin/post.php?post=$rs_id&action=edit"); exit;} 
            else {echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><b>Error</b></p></div>';}
       

	//*******************************

	} else {
		
		//*************************************************
		//ВЫВОД ФОРМЫ ДЛЯ ВВОДА АДРЕСА СТРАНИЦЫ
		global $wpdb;
		$query = "SELECT `post_name` FROM `wp_posts` WHERE `post_status` = 'publish' AND `post_type` = 'post'"; 
		$res=$wpdb->get_results($query, ARRAY_A);
		
		foreach ($res as $res_one){
			$slug_array[]=$res_one[post_name];
		}
		
		// количество циклов по 500 строк
		echo $count_page = file_get_contents("http://web.archive.org/cdx/search/cdx?url=".$rs_domen_name."/&matchType=prefix&showNumPages=true");
				$list_of_pages = '';		
				do {
					$count_page=$count_page-1;
					$list_of_pages = file_get_contents("http://web.archive.org/cdx/search/cdx?url=".$rs_domen_name."/&matchType=prefix&output=json&page=".$count_page);
					$list_of_pages=json_decode($list_of_pages,true); 
					array_shift($list_of_pages);
					rsort($list_of_pages);
					//print_r($list_of_pages);
					foreach ($list_of_pages as $one_page) {
						//if($one_page[4]=='200') {
						//чистим урлы, убираем домен и 80 порт, тоесть убираются дубли страниц с 80 портом, www и http
						if (substr_count($one_page[2],':80')) $one_page[2] = str_replace(':80', '', $one_page[2]);
						
						
						// Если в настройках включено удаление GET переменных, то удаляем 
						if (get_option('rs_del_get_parametr')) {
							if (substr_count($one_page[2],'?')) $one_page[2] = substr($one_page[2], 0, strpos($one_page[2],'?'));
						}
						$tmp_url_id =  substr($one_page[2], strpos($one_page[2], $rs_domen_name)+1+strlen($rs_domen_name));
						if(!$tmp_url_id) {$tmp_url_id='/';}
						$data[$tmp_url_id] = $one_page[3];
						//}
						//$data[$one_page[2]] = $one_page[3];
						//if (!substr_count($one_page[2],'search_type')) $data[$tmp_url_id] = $one_page[3]; исключения из списка файлов и страниц по куску имени
					}
					
					//print_r($data);
					
				} while($count_page > 0);
				 array_multisort($data);
				//print_r($data);
				if($data) {
					?>
		<script type="text/javascript">
		jQuery(document).ready(function(){
		    jQuery("#rs_list tr:not('#rs_list tr:first')").click(function(){ 
		        jQuery(this).find('input').prop("checked", true);
		        jQuery("#rs_list td").removeAttr('style');
		        jQuery(this).find('td').css({"backgroundColor" : "#D6D4D4"});
		    });
		});
		 </script>	
				     <form method="post" target="_blank">
					<table id='rs_list' cellspacing='0' style="margin: 0px auto;">
							<tr>
								<td><b>№</b></td>	
								<td><b>Тип Файла</b></td>
								<td><b>Ссылка</b></td>
								<td><b>Востановить</b></td>
							</tr>
							
							<tr>
								<td></td>	
								<td></td>
								<td>
									<b><a href="http://web.archive.org/web/*/<?=$rs_domen_name?>" target="_blank">Главная страница сайта</a></b>
									(<a style='color:grey;' href="http://<?=$rs_domen_name?>" target="_blank">На сайте</a>) 
									 
								</td>
								<td><input id="title" type='radio' name='rs_url_page' value="<?=$rs_domen_name?>" /></td>	
							</tr>
							
							<?php
							 $i=1;	
							 foreach ($data as $key => $value) { ?> 
							<tr>
								<td><?php echo $i++; ?></td>
								<td><?php echo $value; ?></td>
								<td>
									<a style='<?php echo is_set_slug($key, $slug_array); ?>' href="http://web.archive.org/web/*/<?=$rs_domen_name.'/'.$key?>" target="_blank"><?php echo $key; ?></a>
									(<a style='color:grey;' href="http://<?=get_site_url().'/'.$key?>" target="_blank">На сайте</a>) 
									 
								</td>
								<td><input id="title" type='radio' name='rs_url_page' value="<?=$rs_domen_name.'/'.$key?>" /></td>	
							</tr>	
							<?php } ?>
				</table>
				<div class="submit" style='bottom: 20px;position: fixed;right: 30px;'>
			          <input name="rs_scan" type="submit" class="button-primary" value="<?php echo __('SCAN'); ?>" />
			        </div>
			    </form>
				<?php } else { echo "<br><span class='rs_error'>* В Веб Архиве нет о текущем сайте!</span>";} 
		
		
		
		//*************************************
	}
}


// *****************************
// Страница настроек плагина
function rs_settings() {
    echo "<h2>Настройки плагина <b>RiseSiteWP:	</b></h2>";
    
   // Сохранение настроек
	if (isset($_POST['rs_save_settings'])){
		update_option('rs_domen_name', $_POST['rs_domen_name']);
		update_option('rs_get_title', $_POST['rs_get_title']);
		update_option('rs_title_path', $_POST['rs_title_path']);
		update_option('rs_get_slug', $_POST['rs_get_slug']);
		update_option('rs_slug_path', $_POST['rs_slug_path']);
		update_option('rs_get_date', $_POST['rs_get_date']);
		update_option('rs_date_path', $_POST['rs_date_path']);
		update_option('rs_get_content', $_POST['rs_get_content']);
		update_option('rs_content_path', $_POST['rs_content_path']);
		update_option('rs_get_category', $_POST['rs_get_category']);
		update_option('rs_category_path', $_POST['rs_category_path']);
		update_option('rs_del_get_parametr', $_POST['rs_del_get_parametr']);
	}
	
	// Если в базе нет настроек, то будут установлены по умолчанию
	add_option('rs_domen_name', 'homeschoolmo.com', no);
	add_option('rs_get_title', 'on', no);
	add_option('rs_title_path', '.post-title a', no);
	add_option('rs_get_slug', 'on', no);
	add_option('rs_slug_path', '.html', no);
	add_option('rs_get_date', 'on', no);
	add_option('rs_date_path', '.date-header span', no);
	add_option('rs_get_content', 'on', no);
	add_option('rs_content_path', '.post-body', no);
	add_option('rs_get_category', 'on', no);
	add_option('rs_category_path', '.post-labels a', no);
	add_option('rs_del_get_parametr', '', no);

	printf("<form method='POST' id='rs_list'>
		<table>
			<tbody>
	<!--************************************DOMEN************************************-->		
				<tr>
					<td>
						<label>
						Домен 				
						</label>			
					</td>
					<td>
						<input name='rs_domen_name' id='rs_domen_name' value='%s' />				
					</td>
				</tr>
	<!--************************************TITLE************************************-->				
				<tr>
					<td>
						<label>
						<input name='rs_get_title' id='rs_get_title' type='checkbox' %s />
						Title 				
						</label>			
					</td>
					<td>
						<input name='rs_title_path' id='rs_title_path' value='%s' />				
					</td>
				</tr>
	<!--************************************SLUG************************************-->				
				<tr>
					<td>
						<label>
						<input name='rs_get_slug' id='rs_get_slug' type='checkbox' %s />
						Slug 				
						</label>			
					</td>
					<td>
						<input name='rs_slug_path' id='rs_slug_path' value='%s' />				
					</td>
				</tr>
	<!--************************************DATE************************************-->				
				<tr>
					<td>
						<label>
						<input name='rs_get_date' id='rs_get_date' type='checkbox' %s />
						Date 				
						</label>			
					</td>
					<td>
						<input name='rs_date_path' id='rs_date_path' value='%s' />				
					</td>
				</tr>
	<!--************************************CONTENT************************************-->				
				<tr>
					<td>
						<label>
						<input name='rs_get_content' id='rs_get_content' type='checkbox' %s />
						Content 				
						</label>			
					</td>
					<td>
						<input name='rs_content_path' id='rs_content_path' value='%s' />				
					</td>
				</tr>
	<!--************************************CATEGORY************************************-->				
				<tr>
					<td>
						<label>
						<input name='rs_get_category' id='rs_get_category' type='checkbox' %s />
						Category 				
						</label>			
					</td>
					<td>
						<input name='rs_category_path' id='rs_category_path' value='%s' />				
					</td>
				</tr>
	<!--************************************GET************************************-->				
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_del_get_parametr' id='rs_del_get_parametr' type='checkbox' %s />
						Удалить GET переменные из URL 				
						</label>			
					</td>
				</tr>

				
				<tr>
					<td colspan='2'><input name='rs_save_settings' id='rs_save_settings' type='submit' value='Сохранить' /></td>
				</tr>
		</tbody>
		</table>
	</form>",
	get_option('rs_domen_name'),
	get_option('rs_get_title') ? 'checked=\'checked\'' : '',
	get_option('rs_title_path'),
	get_option('rs_get_slug') ? 'checked=\'checked\'' : '',
	get_option('rs_slug_path'),
	get_option('rs_get_date') ? 'checked=\'checked\'' : '',
	get_option('rs_date_path'),
	get_option('rs_get_content') ? 'checked=\'checked\'' : '',
	get_option('rs_content_path'),
	get_option('rs_get_category') ? 'checked=\'checked\'' : '',
	get_option('rs_category_path'),
	get_option('rs_del_get_parametr') ? 'checked=\'checked\'' : ''
	);
}


//**********************************************************
// Функция востанавливает ссылки удаляя код вебархива
function rise_links($search_text, $message, &$page) {
	$tmp = substr_count($page,$search_text);
	$page = str_replace($search_text, '', $page);
	echo $tmp ? "<br><span class='rs_ok'> * $message (удалено $tmp: \"$search_text\")</span>" : ''; 
	return;
}


//*****************************************
// Проверяет есть ли указаный slug в базе 
function is_set_slug($value, $slug_array){
	reset($slug_array);
	foreach ($slug_array as $slug_one) {
	 	if (!strpos ( $value, '/'.$slug_one.$rs_slug_path )===FALSE) return "color:green;";
	} 
	return;
}

//**************************************************
// Проверка существование внешней ссылки (URL)
function rs_open_url($url) {
	$url_c=parse_url($url);
	//print_r($url_c);
	if (!empty($url_c['host'])) {
   		// Ответ сервера
    	if ($otvet=@get_headers($url)){
      		return substr($otvet[0], 9, 3);
    	}
  	}
  	return 'не найден домен';      
}

?>
