<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Api\System\EntityController;
use App\Http\Controllers\Controller;
use App\Http\Hooks\EntityNotification;
use App\Http\Models\SYSRole;
use Auth;
use View;
use DB;
use Validator;
use Illuminate\Http\Request;
use Redirect;
use Mail;
// models
use App\Http\Models\Admin;
use App\Http\Models\AdminModule;
use App\Http\Models\AdminModulePermission;
use App\Http\Models\Page;
use App\Http\Models\SYSModule;
use App\Http\Models\SYSRolePermissionMap;
use App\Http\Models\SYSPermission;
use App\Http\Models\SYSRolePermission;
use App\Libraries\Module;

class GroupController extends EntityController
{

    private $_object_identifier = "group";
    private $_parent_object_identifier = "role";
    private $_object_identifier_module = "modules";
    private $_object_identifier_entity = "entity_id";
    private $_attribute_pk = "role_id";
    private $_listing_fields = array();
    private $_check_box_checked = "checked='checked'";
    private $_check_box_Unchecked = "";
    private $_child_arrow = "--->";
    private $_model_path = "\App\Http\Models\\";
    private $_model = "";
    /**
     * RoleController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        //$this->middleware('auth');
        // construct parent

        parent::__construct($request);

        $_data = $this->load_params('system/' . $this->_object_identifier . '/listing', 'GET');
        $this->_listing_fields = $_data['records'];
        // role module listing
        $_datas = $this->load_params('system/' . $this->_object_identifier . '/' . $this->_object_identifier_module, 'GET');
        $this->_listing_fields_modules = $_datas['records'];


        // define default dir
        $this->_assignData["dir"] = config("panel.DIR") . $this->_object_identifier . '/';
        // assign meta from parent constructor
        $this->_assignData["_meta"] = $this->__meta;
        // assign request
        $this->_assignData["request"] = $request;
        //module
        $this->_assignData['module'] = trans('system.department');
        $this->_assignData['s_title'] = $this->_assignData['module'];
        $this->_model = new SYSRole();
        //model path
    }

    /**
     * Return data to admin listing page
     *
     * @return type Array()
     */
    public function index(Request $request)
    {
        $this->_assignData['parent_module'] = $this->_parent_object_identifier;
        $this->_assignData['module'] = trans('system.department');
        $this->_assignData['search'] = $this->_listing_fields;
        $this->_assignData['columns']['ids'] = '<div class="checkbox-t"><input type="checkbox" id="check_all" name="check_all" /><label for="check_all"></label></div>';
        foreach ($this->_listing_fields as $listing_field) {
            $this->_assignData['columns'][$listing_field->name] = $listing_field->description;
        }

        $this->_assignData['columns']['options'] = 'Options';

        $data = $this->load_params('system/' . $this->_object_identifier, 'post');
        $this->_assignData['listing_columns'] = $data['records'];

        $this->add($request);


        if ($request->do_post == 1) {
            return $this->_add($request);
        }

        $view = View::make($this->_assignData["dir"] . __FUNCTION__, $this->_assignData);
        return $view;
    }

    /**
     * Ajax Listing
     *
     * @return json
     */


    public function ajaxListing(Request $request)
    {
        // datagrid params : sorting/order
        $search_value = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';
        $dg_order = isset($_REQUEST['order'][0]['column']) ? $_REQUEST['order'][0]['column'] : '';
        $dg_sort = isset($_REQUEST['order'][0]['dir']) ? $_REQUEST['order'][0]['dir'] : '';
        $dg_columns = isset($_REQUEST['columns']) ? $_REQUEST['columns'] : '';

        $search_columns = array();
        $ex2Model = $this->_model_path . "SYSEntityType";
        $ex2Model = new $ex2Model;

   /*     if (trim($search_value) != "") {
            if (isset($_REQUEST['search_columns']) && is_array($_REQUEST['search_columns'])) {
                foreach ($_REQUEST['search_columns'] as $columns) {
                    $search_columns[$columns] = $search_value;

                    if($columns == "entity_type_id"){

                        $entityTypeData = $ex2Model->getEntityTypeByTitle(trim($search_value));
                        if($entityTypeData){
                            $search_columns[$columns] = $entityTypeData->entity_type_id;
                        }

                    }
                }
            }
        }*/
        $search_columns = isset($_REQUEST['search_columns']) ? $_REQUEST['search_columns'] : array();
        //  print_r($search_columns); exit;
        // default ordering
        if ($dg_order == "" && $dg_sort == "") {
            $dg_order = "created_at";
            $dg_sort = "desc";
        } else {
            // fix invalid column
            $dg_order = $dg_order == 0 ? 1 : $dg_order;
            // get column field name
            $dg_order = $dg_columns[$dg_order]["data"];
            // fix joined column name
            $dg_order = str_replace("|", ".", $dg_order);
        }

        // perform select actions


        //$this->_selectActions($request);

        // init output
        $records = array();
        $records["data"] = array();

        if ($request->select_action == 'delete') {
            $this->_selectActions($request);
        }
        $dg_limit = intval($_REQUEST['length']);
        $dg_limit = $dg_limit < 0 ? $total_records : $dg_limit;
        $dg_start = intval($_REQUEST['start']);
        $dg_draw = intval($_REQUEST['draw']);
        $dg_end = $dg_start + $dg_limit;


        $search_columns['limit'] = $dg_limit;
        $search_columns['offset'] = $dg_start;
        $search_columns['order_by'] = $dg_order;
        $search_columns['sorting'] = $dg_sort;
        $search_columns['is_group'] = 1;

       // print_r($search_columns); exit;
        $data = $this->__internalCall($request,\URL::to(DIR_API) . '/system/' . $this->_object_identifier . '/listing', 'GET', $search_columns,false);

        $this->_assignData['records'] = $data->data->{$this->_parent_object_identifier . '_listing'};

        $total_records = $data->data->page->total_records; // total records
        //$total_records = count($query->get()); // total records
        // datagrid settings
        $dg_end = $dg_end > $total_records ? $total_records : $dg_end;

        $paginated_ids = $this->_assignData['records'];
        $default_role_ids = array(1,2,3,4,5,6,7,8,9,10,11);

        // if records
        if (isset($paginated_ids[0])) {
            // Check Permissions
            //$perm_update = $this->_assignData["admin_module_permission_model"]->checkAccess($this->_module, "update", \Session::get($this->_entity_session_identifier.'auth')->admin_group_id);
            //$perm_del = $this->_assignData["admin_module_permission_model"]->checkAccess($this->_module, "delete", \Session::get($this->_entity_session_identifier.'auth')->admin_group_id);
            // collect records
            $i = 0;
            foreach ($paginated_ids as $paginated_id) {

                //$id_record = $this->_model->get($paginated_id->{$this->_pk});

                if(isset($paginated_id->entity_type_id)){

                    $entityTypeData = $ex2Model->getEntityTypeById($paginated_id->entity_type_id);

                    $paginated_id->entity_type_id = $entityTypeData->title;
                }

                // status html
                $status = "";
                // options html
                $options = '<div class="btn-group">';
                // selectbox html
                $checkbox = '<div class="checkbox-t">';
                // manage options
                // - update
                // $options .= '<a class="btn btn-sm btn-default mr5" type="button" href="' . \URL::to($this->_panelPath . $this->_assignData['module'] . '/' . $paginated_id->{$this->_object_identifier . '_id'}) . '" data-toggle="tooltip" title="Permission" data-original-title="Update"><i class="fa fa-lock"></i></a>';

                if(isset($paginated_id->entity_type_id) && $entityTypeData->identifier != 'super_admin') {
                    $options .= '<a class="btn btn-sm btn-default mr5 updateBtn" type="button" href="' . \URL::to($this->_panelPath . $this->_object_identifier . '/update/' . $paginated_id->{$this->_parent_object_identifier . '_id'}) . '" data-toggle="tooltip" title="Update" data-original-title="Update"><i class="fa fa-pencil"></i></a>';

                    if(!in_array($paginated_id->role_id,$default_role_ids)) {
                        $options .= '<a data-module_url="delete" class="btn btn-sm btn-default mr5 grid_action_del delete_action" type="button" data-toggle="tooltip" title="Delete" data-original-title="Delete"><i class="fa fa-times"></i></a>';
                    }
                    $options .= '<a class="btn btn-sm btn-default mr5 updateBtn" type="button" href="' . \URL::to($this->_panelPath . $this->_object_identifier . '/view/' . $paginated_id->{$this->_parent_object_identifier . '_id'}) . '" data-toggle="tooltip" title="View" data-original-title="View"><i class="fa fa-eye"></i></a>';

                }

                $checkbox .= '<input type="checkbox" id="check_id_' . $paginated_id->{$this->_parent_object_identifier . '_id'} . '" name="check_ids[]" value="' . $paginated_id->{$this->_parent_object_identifier . '_id'} . '" />';
                $checkbox .= '<label class="deleted_btn" for="check_id_' . $paginated_id->{$this->_parent_object_identifier . '_id'} . '"></label>';
                $options .= '</div>';
                $checkbox .= '</div>';

                $list["ids"] = $checkbox;

                // collect data
                foreach ($this->_listing_fields as $listing_field) {
                    switch ($listing_field->name) {
                        case "created_at":
                        case "updated_at":
                            $list[$listing_field->name] = date(DATE_FORMAT_ADMIN, strtotime($paginated_id->{$listing_field->name}));
                            break;
                        default:
                            $list[$listing_field->name] = empty($paginated_id->{$listing_field->name}) ? '' : $paginated_id->{$listing_field->name};
                            break;
                    }
                }
                $list["options"] = $options;
                $records["data"][] = $list;
                // increament
                $i++;
            }
        }

        $records["draw"] = $dg_draw;
        $records["recordsTotal"] = $total_records;
        $records["recordsFiltered"] = $total_records;

        echo json_encode($records);
    }

    public function roleAjaxListing(Request $request)
    {
        $search_value = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';
        $dg_order = isset($_REQUEST['order'][0]['column']) ? $_REQUEST['order'][0]['column'] : '';
        $dg_sort = isset($_REQUEST['order'][0]['dir']) ? $_REQUEST['order'][0]['dir'] : '';
        $dg_columns = isset($_REQUEST['columns']) ? $_REQUEST['columns'] : '';

        $search_columns = array();
        if (trim($search_value) != "") {
            if (isset($_REQUEST['search_columns']) && is_array($_REQUEST['search_columns'])) {
                foreach ($_REQUEST['search_columns'] as $columns) {
                    $search_columns[$columns] = $search_value;
                }
            }
        }

        // default ordering
        if ($dg_order == "" && $dg_sort == "") {
            $dg_order = "created_at";
            $dg_sort = "ASC";
        } else {
            // fix invalid column
            $dg_order = $dg_order == 0 ? 1 : $dg_order;
            // get column field name
            $dg_order = $dg_columns[$dg_order]["data"];
            // fix joined column name
            $dg_order = str_replace("|", ".", $dg_order);
        }

        $records = array();
        $records["data"] = array();

        // init output
        $total_records = 0;
        $dg_limit = intval($_REQUEST['length']);
        $dg_limit = $dg_limit < 0 ? $total_records : $dg_limit;
        $dg_start = intval($_REQUEST['start']);
        $dg_draw = intval($_REQUEST['draw']);
        $dg_end = $dg_start + $dg_limit;
        $search_columns['limit'] = $dg_limit;
        $search_columns['offset'] = $dg_start;

        $rolePermission = new SYSRolePermission();

        $module = new Module();
        $sys_modules = $module->getAllMenus(0);

        foreach($sys_modules as $sys_module){
            foreach ($this->_listing_fields_modules as $listing_field) {
                $is_checked = false;
                if(isset($request->role_id)){
                    $is_checked = $rolePermission->checkAccess($sys_module->module_id,$listing_field->name,$request->role_id);
                }
                $permis[$listing_field->name] = $this->getCheckBox($listing_field->name,
                    $sys_module->module_id,$is_checked);
            }
            $permis['modules'] = $sys_module->title;
            $records["data"][] = $permis;

            $sub_modules = $module->getAllMenus($sys_module->module_id);

            if($sub_modules){
                foreach($sub_modules as $sub_module){
                    foreach ($this->_listing_fields_modules as $listing_field) {
                        $is_checked = false;
                        if(isset($request->role_id)){
                            $is_checked = $rolePermission->checkAccess($sub_module->module_id,$listing_field->name,$request->role_id);
                        }
                        $permis[$listing_field->name] = $this->getCheckBox($listing_field->name,
                            $sub_module->module_id,$is_checked);
                    }
                    $permis['modules'] = ' ----> '.$sub_module->title;
                    $records["data"][] = $permis;
                }
            }
        }


        $records["draw"] = $dg_draw;
        $records["recordsTotal"] = $total_records;
        $records["recordsFiltered"] = $total_records;
        echo json_encode($records);
    }

    private function getCheckBox($identifier , $module_id ,$checked=false)
    {
        if($checked) $checked='checked="checked"'; else $checked='';
        $checkbox_per = '<div class="checkbox-t">';
        $checkbox_per .= '<input type="checkbox" ' . $checked . '  id="'.$identifier.$module_id.'" name="'.$identifier.'[]" value="'.$module_id.'" />';
        $checkbox_per .= '<label class="deleted_btn"  for="' . $identifier . $module_id. '"></label>';
        $checkbox_per .= '</div>';
        return $checkbox_per;
    }


    /**
     * Add
     *
     * @return view
     */
    public function add(Request $request)
    {

        //Checking module Authentication
        // page action
        $this->_assignData["dir"] = config("panel.DIR") . $this->_object_identifier . '/';
        $data = $this->load_params('system/' . $this->_object_identifier, 'post');
       //echo "<pre>"; print_r($data['records']); exit;
        $this->_assignData['records'] = $data['records'];

        $this->_assignData["page_action"] = ucfirst(__FUNCTION__);
        $this->_assignData["route_action"] = strtolower(__FUNCTION__);


        $view = View::make($this->_assignData["dir"] . __FUNCTION__, $this->_assignData);
        return $view;
    }

    /**
     * Add (private)
     *
     * @return view
     */
    private function _add(Request $request)
    {
        $return = $this->_checkActionPermission($this->_object_identifier,'add');
        if(isset($return['error']) && $return['error'] == 1){
            return $return;
        }

        //Get specific role entity type id
        if(!isset($request->entity_type_id))
            $entity_type_id = $this->_model->getEntityTypeIDBySlug('business_user');
        else
            $entity_type_id = $request->entity_type_id;

        $request->request->add(['entity_type_id'=>$entity_type_id,'is_group' => 1,'created_at' => date("Y-m-d H:i:s")]);

        $ret = $this->__internalCall($request,\URL::to(DIR_API) . '/system/' . $this->_parent_object_identifier, 'POST', $request->all(),false);

        if ($ret->error == '1') {
            $assignData['error'] = 1;
            $assignData['message'] = $ret->message;
            return $assignData;
        } else {

            $assignData['error'] = 0;
            $assignData['message'] = $ret->message;
            $assignData['redirect'] = \URL::to($this->_panelPath . $this->_object_identifier);
            return $assignData;
        }

    }

    /**
     * Update
     *
     * @return view
     */
    public function update(Request $request, $department, $id)
    {
        // page action
        $this->_assignData["page_action"] = ucfirst(__FUNCTION__);
        $this->_assignData["route_action"] = strtolower(__FUNCTION__);

        $array = explode('/',$request->url());
        end($array);
        $this->_assignData["uri_method"] =  prev($array);


        $getData[$this->_parent_object_identifier . '_id'] = $id;
        $this->_assignData["update"] = $this->__internalCall($request,\URL::to(DIR_API) . '/system/' . $this->_parent_object_identifier, 'GET', $getData);

       // echo "<pre>"; print_r($this->_assignData["update"]); exit;
        $this->_assignData["dir"] = config("panel.DIR") . $this->_object_identifier . '/';
        $data = $this->load_params('system/' . $this->_object_identifier . '/update', 'post');


        $this->_assignData['records'] = $data['records'];

        // get record
        // $this->_assignData["data"] = $this->_model->get($id);

        foreach ($this->_listing_fields_modules as $listing_field) {
            $this->_assignData['columns'][$listing_field->name] = $listing_field->description;
        }

        // validate post form
        if (isset($request->do_post)) {
            return $this->_update($request);
        }
        // redirect on invalid record
        if ($this->_assignData["update"] == FALSE) {
            // set session msg
            \Session::put(ADMIN_SESS_KEY . 'error_msg', 'Invalid record selection');
            // redirect
            $this->_assignData['redirect'] = \URL::to($this->_panelPath . $this->_object_identifier);
            return $this->_assignData;
        }

        $view = View::make($this->_assignData["dir"] . __FUNCTION__, $this->_assignData);
        return $view;
    }

    /**
     * Update (private)
     *
     * @return view
     */
    private function _update(Request $request)
    {
        //$this->_assignData["data"] = $_POST;
        $_POST['is_group'] = 1;
        $this->_assignData["update"] = $this->__internalCall($request,\URL::to(DIR_API) . '/system/' . $this->_parent_object_identifier . '/update', 'POST', $_POST,false);

        if ($this->_assignData["update"]->error == "1") {
            $assignData['error'] = 1;
            $assignData['message'] = $this->_assignData["update"]->message;
            return $assignData;
        } else {

            $assignData['error'] = 0;
            $assignData['message'] = $this->_assignData["update"]->message;
            $assignData['redirect'] = \URL::to($this->_panelPath . $this->_object_identifier);
            return $assignData;
        }
        //return $this->_update($request, $this->_assignData["data"]);
    }

    /**
     * Select Action
     *
     * @return query
     */
    private function _selectActions($request)
    {
        $request->select_action = trim($request->select_action);
        $request->checked_ids = is_array($request->checked_ids) ? $request->checked_ids : array();

        if ($request->select_action != "" && isset($request->checked_ids[0])) {
            foreach ($request->checked_ids as $checked_id) {

                $postData[$this->_attribute_pk] = $checked_id;
                $data = $this->apiPostRequest(\URL::to(DIR_API) . '/system/' . $this->_parent_object_identifier . '/delete', 'POST', $postData);

            }
        }
    }

    /**
     * Check action permission for advance template
     * where add action is calling inside listing action
     * @param $page
     * @param $action
     * @return mixed
     */
    private function _checkActionPermission($page,$action)
    {
        $module_lib = new Module();
        $module_lib->checkActionPermission($page,$action);
    }

    /**
     * View Page
     * @param Request $request
     * @param $department
     * @param $id
     * @return mixed
     */
    public function view(Request $request, $department, $id)
    {
        // page action
        $this->_assignData["page_action"] = ucfirst(__FUNCTION__);
        $this->_assignData["route_action"] = strtolower(__FUNCTION__);

        //check if request from notification then update is read
        $entity_notification = new \App\Libraries\EntityNotification();
        $entity_notification->updateNotificationRead($request->all());

        $getData[$this->_parent_object_identifier . '_id'] = $id;
        $update = $this->__internalCall($request,\URL::to(DIR_API) . '/system/' . $this->_parent_object_identifier, 'GET', $getData);

        $this->_assignData["update"] = $update->data->{$this->_parent_object_identifier};


        if($this->_assignData["update"]){

            //Get Parent title
            if($this->_assignData["update"]->parent_id > 0){
                $this->_assignData["update"]->parent_id = $this->_model->getRoleTitleById($this->_assignData["update"]->parent_id);
            }
        }

        $this->_assignData["dir"] = config("panel.DIR") . $this->_object_identifier . '/';
        $data = $this->load_params('system/' . $this->_object_identifier . '/update', 'post');


        $this->_assignData['records'] = $data['records'];


        // redirect on invalid record
        if ($this->_assignData["update"] == FALSE) {
            // set session msg
            \Session::put(ADMIN_SESS_KEY . 'error_msg', 'Invalid record selection');
            // redirect
            $this->_assignData['redirect'] = \URL::to($this->_panelPath . $this->_assignData['module']);
            return $this->_assignData;
        }


        $view = View::make($this->_assignData["dir"] . __FUNCTION__, $this->_assignData);
        return $view;
    }


}