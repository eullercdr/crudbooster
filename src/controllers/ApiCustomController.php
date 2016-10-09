<?php namespace crocodicstudio\crudbooster\controllers;

use crocodicstudio\crudbooster\controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\PDF;
use Illuminate\Support\Facades\Excel;

class ApiCustomController extends CBController {

	
	public function __construct() {		
		$this->table       = 'cms_apicustom';
		$this->primary_key = 'id';
		$this->title_field = "nama";
		$this->button_show_data = false;
		$this->button_new_data = false;
		$this->button_delete_data = false;			
		$this->constructor();
	}


	function getIndex() {		

		$data = array();
		
		$data['page_title'] = 'API Generator';
		$data['page_menu']  = Route::getCurrentRoute()->getActionName();
		$data['apis']       = DB::table('cms_apicustom')->orderby('nama','asc')->get();

		return view('crudbooster::api_documentation',$data); 
	}

	public function getScreetKey() {
		
		$data['page_title'] = 'API Generator';
		$data['page_menu']  = Route::getCurrentRoute()->getActionName();
		$data['apikeys'] = DB::table('cms_apikey')->get();
		return view('crudbooster::api_key',$data); 
	}

	public function getGenerator() {

		$data['page_title'] = 'API Generator';
		$data['page_menu']  = Route::getCurrentRoute()->getActionName();

		$tables = list_tables();
		$tables_list = array();
		foreach($tables as $tab) {
			foreach ($tab as $key => $value) {
				$tables_list[] = $value;				
			}
		}

		$data['tables']     = $tables_list;

		return view('crudbooster::api_generator',$data); 
	}

	public function getEditApi($id) {

		$row = DB::table('cms_apicustom')->where('id',$id)->first();

		$data['row']        = $row;
		$data['parameters'] = json_encode(unserialize($row->parameters));
		$data['responses']  = json_encode(unserialize($row->responses));
		$data['page_title'] = 'API Generator';
		$data['page_menu']  = Route::getCurrentRoute()->getActionName();

		$tables      = list_tables();
		$tables_list = array();
		foreach($tables as $tab) {
			foreach ($tab as $key => $value) {
				$tables_list[] = $value;				
			}
		}

		$data['tables']     = $tables_list;

		return view('crudbooster::api_generator',$data); 
	}
 
	function getGenerateScreetKey() {				

		//Generate a random string.
		$token = openssl_random_pseudo_bytes(16);
		 
		//Convert the binary data into hexadecimal representation.
		$token = bin2hex($token);

		$id = DB::table('cms_apikey')
		->insertGetId([
			'screetkey'=>$token,
			'created_at'=>date('Y-m-d H:i:s'),
			'status'=>'active',
			'hit'=>0
			]);

		$response        = array();
		$response['key'] = $token;
		$response['id']  = $id;

		return response()->json($response);
	}

	public function getStatusApikey() {
		valid(['id','status'],'view');

		$id = Request::get('id');
		$status = (Request::get('status')==1)?"active":"non active";

		DB::table('cms_apikey')->where('id',$id)->update(['status'=>$status]);

		return redirect()->back()->with(['message'=>'You have been update api key status !','message_type'=>'success']);
	}

	public function getDeleteApiKey() {		

		$id = Request::get('id');
		if(DB::table('cms_apikey')->where('id',$id)->delete()) {
			return response()->json(['status'=>1]);
		}else{
			return response()->json(['status'=>0]);
		}
	}


	function getColumnTable($table,$type='list') {
		$result = array();

		$cols = DB::getSchemaBuilder()->getColumnListing($table);
		
		$except = ['created_at','deleted_at','updated_at'];

		$result = $cols;		
		$new_result = array(); 
		foreach($result as $ro) {

			if(in_array($ro, $except)) continue;

			$type_field = get_field_type($table,$ro);

			$type_field = (array_search($ro, explode(',',config('crudbooster.EMAIL_FIELDS_CANDIDATE')) )!==FALSE)?"email":$type_field;
			$type_field = (array_search($ro, explode(',',config('crudbooster.IMAGE_FIELDS_CANDIDATE')) )!==FALSE)?"image":$type_field;
			$type_field = (array_search($ro, explode(',',config('crudbooster.PASSWORD_FIELDS_CANDIDATE')) )!==FALSE)?"password":$type_field;

			$type_field = (substr($ro, -3) == '_id')?"integer":$type_field;
			$type_field = (substr($ro, 0, 3) == 'id_')?"integer":$type_field;

			$new_result[] = array('name'=>$ro,'type'=>$type_field);

			if($type=='list' || $type=='detail') {
				if(substr($ro,0,3)=='id_') {
					$table2 = substr($ro,3);
					$t2 = DB::getSchemaBuilder()->getColumnListing($table2);
					foreach($t2 as $t) {
						if($t!='id' && $t!='created_at' && $t!='updated_at' && $t!='deleted_at') {

							if(substr($t, 0,3) == 'id_') continue;

							$type_field   = get_field_type($table2,$t);
							$t            = str_replace("_$table2","",$t);
							$new_result[] = array('name'=>$table2.'_'.$t,'type'=>$type_field);
						}
					}
				}
			}
		}		
		
		return response()->json($new_result);
	}

	function postSaveApiCustom() {

		$posts = Request::all();		

		$a = array();		

		$a['nama']        = g('nama');
		$a['tabel']       = Request::input('tabel');
		$a['aksi']        = Request::input('aksi');
		$a['permalink']   = g('permalink');
		$a['method_type'] = g('method_type');
		
		$params_name     = g('params_name');
		$params_type     = g('params_type');
		$params_config   = g('params_config');
		$params_required = g('params_required');
		$params_used     = g('params_used');
		$json            = array();
			
		for($i=0;$i<=count($params_name);$i++) {
			if($params_name[$i]) {
				$json[] = array(
				'name'=>$params_name[$i],
				'type'=>$params_type[$i],
				'config'=>$params_config[$i],
				'required'=>$params_required[$i],
				'used'=>$params_used[$i]
				);
			}			
		}

		$json = array_filter($json);
		$a['parameters'] = serialize($json);

		$a['sql_where'] = g('sql_where');

		$responses_name = g('responses_name');
		$responses_type = g('responses_type');
		$responses_subquery = g('responses_subquery');
		$responses_used = g('responses_used');
		$json = array();
		for($i=0;$i<=count($responses_name);$i++) {
			if($responses_name[$i]) {
				$json[] = array(
					'name'=>$responses_name[$i],
					'type'=>$responses_type[$i],
					'subquery'=>$responses_subquery[$i],
					'used'=>$responses_used[$i]					
					);
			}
		}

		$json            = array_filter($json);
		$a['responses']  = serialize($json);
		$a['keterangan'] = g('keterangan');

		if(Request::get('id')) {
			DB::table('cms_apicustom')->where('id',g('id'))->update($a);			
		}else{

			$controllerName = ucwords(str_replace('_',' ',$a['permalink']));
			$controllerName = str_replace(' ', '', $controllerName);			
			generate_api($controllerName,$a['tabel'],$a['permalink'],$a['method_type']);

			DB::table('cms_apicustom')->insert($a);
		}				

		return redirect(mainpath())->with(['message'=>'Yeay, your api has been saved successfully !','message_type'=>'success']);

	}

	function getDeleteApi($id) {
		$row = DB::table('cms_apicustom')->where('id',$id)->first();
		DB::table('cms_apicustom')->where('id',$id)->delete();

		$controllername = ucwords(str_replace('_',' ',$row->permalink));
		$controllername = str_replace(' ', '', $controllername);
		@unlink(base_path("app/Http/Controllers/Api".$controllername."Controller.php"));

		return response()->json(['status'=>1]);
	}

	

}
