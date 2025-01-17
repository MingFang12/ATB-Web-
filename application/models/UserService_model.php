<?php

/**
 * Created by PhpStorm.
 * User: zeus
 * Date: 2019/7/22
 * Time: 6:39 AM
 */
class UserService_model extends MY_Model
{
    public function insertNewServiceInfo($ins) {
        if($this->db->insert(self::TABLE_SERVICE_INFO_LIST, $ins)) {
            return $this->db->insert_id();
        }
        else {
            return -1;
        }
    }

    public function updateServiceRecord($setArray, $whereArray) {
        $this->db->where($whereArray);
        $this->db->update(self::TABLE_SERVICE_INFO_LIST, $setArray);
    }

    public function getServiceInfoList($userId) {
        $services = $this->db->select('*') -> from(self::TABLE_SERVICE_INFO_LIST)
                    ->where(array('user_id' => $userId, 'approved'=>1))
                    ->where('is_active !=', '99') // deleted
                    ->where('is_active !=', '98') // draft
                    ->get()
                    ->result_array();
       
        for($i = 0 ; $i < count($services) ; $i++) {
                    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
        }            
        return $services;            
    }

    public function getServiceDrafts($userId) {
        $services = $this->db->select('*') -> from(self::TABLE_SERVICE_INFO_LIST)
                    ->where(array('user_id' => $userId, 'is_active'=>98))
                    ->get()
                    ->result_array();
       
        for($i = 0 ; $i < count($services) ; $i++) {
                    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
        }            
        return $services; 
    }
	
	public function getServiceInfoAllList($userId) {
        $services = $this->db->select('*') -> from(self::TABLE_SERVICE_INFO_LIST)
                    ->where(array('user_id' => $userId))
                    ->where('is_active !=', '99')
                    ->where('is_active !=', '98') // draft
                    ->get()
                    ->result_array();
        for($i = 0 ; $i < count($services) ; $i++) {
        	    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
        }            
        return $services; 
    }
	
	public function getServiceInfoNotApprovedList() {
        $services = $this->db->select('*') -> from(self::TABLE_SERVICE_INFO_LIST)
                    ->where(array('approved'=>0))
                    ->where('is_active !=', '99')
                    ->where('is_active !=', '98') // draft
                    ->get()
                    ->result_array();
        for($i = 0 ; $i < count($services) ; $i++) {
                    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
        }            
        return $services; 
    }

    public function getServiceInfo($id) {
        $services = $this->db->select('*')
            -> from(self::TABLE_SERVICE_INFO_LIST)
            ->where(array('id' => $id))
            ->get()
            ->result_array();
            
        for($i = 0 ; $i < count($services) ; $i++) {
                    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
                    $user = $this->User_model->getOnlyUser(array('id' => $services[$i]['user_id']));
                    $services[$i]["user"] = $user;
        }            
        return $services; 
    }

    public function removeCardRecord($where) {
        $this->db->where($where)->delete(self::TABLE_SERVICE_INFO_LIST);
    }
    
    public function getPostImage($where) {
        return $this->db->select('*')
                            ->from(self::TABLE_SERVICE_IMGS)
                            ->where($where)
                            ->get()
                            ->result_array();
    }
    
    public function removePostImg($where) {
        $this->db->where($where)->delete(self::TABLE_SERVICE_IMGS);
    }
    
    public function insertNewImage($ins) {
        if($this->db->insert(self::TABLE_SERVICE_IMGS, $ins)) {
            return $this->db->insert_id();
        }
        else {
            return -1;
        }
    }


    public function getServiceInfos($where = array()) {
        $services = $this->db->select('*') -> from(self::TABLE_SERVICE_INFO_LIST)
                    ->where($where)
                    ->where('is_active !=', '99')
                    ->where('is_active !=', '98') // draft
                    ->get()
                    ->result_array();
        for($i = 0 ; $i < count($services) ; $i++) {
                    $services[$i]["insurance"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['insurance_id']);
                    $services[$i]["qualification"] = $this->UserServiceFiles_model->getServiceFile($services[$i]['qualification_id']);
                    $services[$i]['post_imgs'] = $this->getPostImage(array('service_id' => $services[$i]['id']));
                    $user = $this->User_model->getOnlyUser(array('id' => $services[$i]['user_id']));
                    $services[$i]["user"] = $user;
        }            
        return $services; 
    }

    public function updatePostContent($set, $where) {
        $this->db->where($where);
        $this->db->update(self::TABLE_SERVICE_INFO_LIST, $set);
       
        $setArray = array(
            'is_active' => $set['is_active'],
            'status_reason' => $set['approval_reason'],
            'updated_at' => time(),
        );
        $whereArray = array('service_id' =>$where['id']);
        
        $this->Post_model->updatePostContent($setArray, $whereArray);
    }

}
