<?php

	class qa_html_theme_layer extends qa_html_theme_base {

		function option_default($option) {
			
			switch($option) {
				case 'signatures_length':
					return 1000;
					break;
				case 'signatures_header':
					return '<br>';
					break;
				case 'signatures_footer':
					return '';
					break;
				default:
					return false;				
					break;
			}
			
		}
		
		var $signatures;
		
	// theme replacement functions

		function head_script()
		{
			qa_html_theme_base::head_script();
			if($this->template == 'user' && qa_opt('signatures_enable')) {
				$handle = preg_replace('/^[^\/]+\/([^\/]+).*/',"$1",$this->request);
				if(qa_get_logged_in_handle() == $handle) {
					$this->output_raw('<script src="'.QA_HTML_THEME_LAYER_URLTOROOT.'textLimitCount.js" type="text/javascript"></script>');
					$this->output_raw("
<script>
	var signature_max_length = ".(qa_opt('signatures_length')?qa_opt('signatures_length'):1000).";
	jQuery('document').ready(function(){
		$('textarea[name=\"signature_text\"]').textLimiter({
		maxLength: signature_max_length,
		elCount: 'elCount'
	  });
	});
</script>");
				}
			}
		}
	
		function main_parts($content)
		{
			if (qa_opt('signatures_enable')) {

				// add user signature

				if($this->template == 'user') { 
					if($content['q_list']) {  // paranoia
					
						// array splicing kungfu thanks to Stack Exchange
						
						// This adds form-signature before q_list
					
						$keys = array_keys($content);
						$vals = array_values($content);

						$insertBefore = array_search('q_list', $keys);

						$keys2 = array_splice($keys, $insertBefore);
						$vals2 = array_splice($vals, $insertBefore);

						$keys[] = 'form-signature';
						$vals[] = $this->user_signature_form();

						$content = array_merge(array_combine($keys, $vals), array_combine($keys2, $vals2));
					}
					else $content['form-signature'] = $this->user_signature_form();  // this shouldn't happen

				}
			}

			qa_html_theme_base::main_parts($content);

		}
		function q_view_content($q_view)
		{

			if (qa_opt('signatures_enable') && qa_opt('signatures_q_enable')) {
				$result = qa_db_read_all_assoc(
					qa_db_query_sub(
						'SELECT signature,userid FROM ^usersignatures'
					)
				);
				
				foreach($result as $user) {
					if (!empty($user['signature'])) {
						
						$formats = qa_list_modules('editor');
						$editorname = $formats[qa_opt('signatures_format')];
						$editor=qa_load_module('editor', $editorname);
						$readdata=$editor->read_post();
						$informat=$readdata['format'];					
						
						$viewer=qa_load_viewer($user['signature'], $informat);
						
						global $options;
						
						$signature=$viewer->get_html($user['signature'], $informat, array(
							'blockwordspreg' => @$options['blockwordspreg'],
							'showurllinks' => @$options['showurllinks'],
							'linksnewwindow' => @$options['linksnewwindow'],
						));
					}
					$this->signatures[$user['userid']] = $signature;
				}
				
				if(isset($this->signatures[$q_view['raw']['userid']])) $q_view['content'].=qa_opt('signatures_header').$this->signatures[$q_view['raw']['userid']].qa_opt('signatures_footer');
			}
			
			qa_html_theme_base::q_view_content($q_view);

		}
		function a_item_content($a_item)
		{
			if (qa_opt('signatures_enable') && qa_opt('signatures_a_enable')) {
				if(isset($this->signatures[$a_item['raw']['userid']])) $a_item['content'].=qa_opt('signatures_header').$this->signatures[$a_item['raw']['userid']].qa_opt('signatures_footer');
			}
			qa_html_theme_base::a_item_content($a_item);

		}
		function c_item_content($c_item)
		{
			if (qa_opt('signatures_enable') && qa_opt('signatures_c_enable')) {
				if(isset($this->signatures[$c_item['raw']['userid']])) $c_item['content'].=qa_opt('signatures_header').$this->signatures[$c_item['raw']['userid']].qa_opt('signatures_footer');
			}
			qa_html_theme_base::c_item_content($c_item);
		}
		
	// worker functions

		function user_signature_form() {
			// displays signature form in user profile
			
			global $qa_request;
			
			$handle = preg_replace('/^[^\/]+\/([^\/]+).*/',"$1",$qa_request);
			
			$userid = $this->getuserfromhandle($handle);
			
			if(!$userid) return;

			$ok = null;
			
			$formats = qa_list_modules('editor');
			
			foreach ($formats as $key => $format) 
				if(!strlen($format)) 
					$formats[$key] = qa_lang_html('admin/basic_editor');
			
			$editorname = $formats[qa_opt('signatures_format')];
			$editor=qa_load_module('editor', $editorname);
			$readdata=$editor->read_post('signature_text');
			$informat=$readdata['format'];
			
			if (qa_clicked('signature_save')) {
				if(strlen(qa_post_text('signature_text')) > qa_opt('signatures_length')) {
					$error = 'Max possible signature length is 1000 characters';
				}
				else {

					// formatting
							
					$incontent = qa_post_text('signature_text');
					$viewer = qa_load_viewer($incontent, $informat);
					$outtext = $viewer->get_text($incontent, $informat, array());				
					
					qa_db_query_sub(
						'INSERT INTO ^usersignatures (userid,signature) VALUES (#,$) ON DUPLICATE KEY UPDATE signature=$',
						$userid,$outtext,$outtext
					);
					$ok = 'Signature Saved.';
				}
			}
			
			$content = qa_db_read_one_value(
				qa_db_query_sub(
					'SELECT signature FROM ^usersignatures WHERE userid=#',
					$userid
				),
				true
			);
			
			if(qa_get_logged_in_handle() == $handle) {
				
				$form=array(
				
					'ok' => ($ok && !isset($error)) ? $ok : null,
					
					'style' => 'tall',
					
					'title' => '<a name="signature_text">Signature</a>',

					'tags' =>  'action="'.qa_self_html().'#signature_text" method="POST"',
					
					'fields' => array(
						
						'content' => $editor->get_field($this->content, $content, $informat, 'signature_text', 12, true),

						'elCount' => array(
							'label' => '<div id="elCount">'.qa_opt('signatures_length').'</div>',
							'type' => 'static',
							)
					),
					
					'buttons' => array(
						array(
							'label' => qa_lang_html('main/save_button'),
							'tags' => 'NAME="signature_save"',
						),
					),
					
					'hidden' => array(
						'editor' => qa_html($editorname),
						'dosavesig' => '1',
					),
				);
				return $form;
			}
			else if($content && qa_opt('signatures_profile_enable')) {
				
				$viewer=qa_load_viewer($content, $informat);
				
				global $options;
				
				$signature=$viewer->get_html($content, $informat, array(
					'blockwordspreg' => @$options['blockwordspreg'],
					'showurllinks' => @$options['showurllinks'],
					'linksnewwindow' => @$options['linksnewwindow'],
				));

				$fields[] = array(
						'label' => qa_opt('signatures_header').$signature.qa_opt('signatures_footer'),
						'type' => 'static',
				);
				return array(
					'title' => 'Signature',
					'fields' => $fields
				);
			}				
			
		}
		function getuserfromhandle($handle) {
			require_once QA_INCLUDE_DIR.'qa-app-users.php';
			
			if (QA_FINAL_EXTERNAL_USERS) {
				$publictouserid=qa_get_userids_from_public(array($handle));
				$userid=@$publictouserid[$handle];
				
			} 
			else {
				$userid = qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT userid FROM ^users WHERE handle = $',
						$handle
					),
					true
				);
			}
			if (!isset($userid)) return;
			return $userid;
		}
				
		
	}

