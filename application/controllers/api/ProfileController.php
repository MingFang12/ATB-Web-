<?php

/**
 * Created by PhpStorm.
 * User: zeus
 * Date: 2019/7/11
 * Time: 2:58 PM
 */
class ProfileController extends MY_Controller
{
	
	public function set_transaction_booking_id()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$bookingId = $this->input->post('booking_id');
			$this->UserBraintreeTransaction_model->updateTransactionRecord(
				array(
					'target_id' => $bookingId,
					'purchase_type' => 'booking'
				),
				array('id' => $this->input->post('transaction_id'))
			);

			$bookings = $this->Booking_model->getBooking($bookingId);
			if (count($bookings) > 0) {
				$services= $this->UserService_model->getServiceInfo($bookings[0]['service_id']);
				$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
				$amount = (float)$services[0]['deposit_amount'];

				$this->NotificationHistory_model->insertNewNotification(
					array(
						'user_id' => $services[0]['user_id'],
						'type' => 6,
						'related_id' => $bookingId,
						'read_status' => 0,
						'send_status' => 0,
						'visible' => 1,
						'text' =>  " has booked " . $services[0]['title'] . " and paid a deposit of £" . number_format($amount, 2),
						'name' => $users[0]['user_name'],
						'profile_image' => $users[0]['pic_url'],
						'updated_at' => time(),
						'created_at' => time()
					)
				);
			}			

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Updated successfully";

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_multi_group_id()
	{
		$verifyTokenResult = $this->verificationToken($this->input->post('token'));
		$retVal = [];

		if ($verifyTokenResult[self::RESULT_FIELD_NAME]) {
			$multi_group = 0;

			if ($this->Product_model->isCurrentlyUploadingMultiGroup($verifyTokenResult['id'])) {
				$multi_group = $this->Product_model->getCurrentMultiGroup($verifyTokenResult['id']);
			} else {
				$multi_group = $this->Product_model->getNextMultiGroup();
			}

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $multi_group;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
			$retVal[self::EXTRA_FIELD_NAME] = null;
		}

		echo json_encode($retVal);
	}
	
	public function update_search_region()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->User_model->updateUserRecord(
				array(
					'post_search_region' => $this->input->post('range'),
					'latitude' => $this->input->post('lat'),
					'longitude' => $this->input->post('lng'),
					'country' => $this->input->post('address')
				),
				array('id' => $tokenVerifyResult['id'])
			);
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Updated successfully";
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getprofile()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$profile = $this->User_model->getUserProfileDTO($this->input->post('user_id'));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $profile;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}
		echo json_encode($retVal);
	}

	public function adduserbookmark()
	{
		$this->load->model('UserBookmark_model');

		$verifyTokenResult = $this->verificationToken($this->input->post('token'));
		$retVal = [];

		if ($verifyTokenResult[self::RESULT_FIELD_NAME]) {
			$bookMarks = $this->UserBookmark_model->getUserBookmarks(
				array(
					'user_id' => $verifyTokenResult['id'],
					'post_id' => $this->input->post('post_id')
				)
			);
			if (count($bookMarks) > 0) {
				$this->UserBookmark_model->deleteBookmark(
					array(
						'user_id' => $verifyTokenResult['id'],
						'post_id' => $this->input->post('post_id')
					)
				);
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Bookmark removed";
			} else {
				$insResult = $this->UserBookmark_model->insertNewUserBookmark(
					array(
						'user_id' => $verifyTokenResult['id'],
						'post_id' => $this->input->post('post_id'),
						'updated_at' => time(),
						'created_at' => time()
					)
				);
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully published";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
			$retVal[self::EXTRA_FIELD_NAME] = null;
		}

		echo json_encode($retVal);
	}

	public function getuserbookmarks()
	{
		$this->load->model('UserBookmark_model');
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$bookmarks = $this->UserBookmark_model->getUserBookmarks(array('user_id' => $this->input->post('user_id')));
            
            $bookmarkedPosts = array();
            for ($i = 0; $i < count($bookmarks); $i++) {
                $postContent = $this->Post_model->getPostDetail($bookmarks[$i]['post_id'], $tokenVerifyResult['id']);  
            
                $product_id = $postContent['product_id'];                        
                if ($postContent['post_type'] == "2" && !is_null($product_id) && !empty($product_id)) {
                    $postContent["variations"] = $this->Product_model->getProductVariations(array('product_id' => $product_id));
                }
                
                $tagids = $this->Tag_model->getPostTags($postContent['id']);
                
                $tags = array();
                foreach ($tagids as $tagid) {
                    $tags[] = $this->Tag_model->getTag($tagid['tag_id']);
                }
                $postContent["tags"] = $tags;
                
                $bookmarkedPosts[]= $postContent;
            }
            
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $bookmarkedPosts;
            
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_notifications() {
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
        $retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$notifications = $this->NotificationHistory_model->getNotificationHistory(array('user_id' => $tokenVerifyResult['id']));
			
            $retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $notifications;
            
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
    
    public function read_notification() {
        $tokenVerifyResult = $this->verificationToken($this->input->post('token'));
        
        $retVal = array();
        if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {            
            $this->NotificationHistory_model->updateNotificationHistory(
                array('read_status' => '1'), 
                array('id' => $this->input->post('notification_id')));
            
            $retVal[self::RESULT_FIELD_NAME] = true;
            $retVal[self::MESSAGE_FIELD_NAME] = "You read the notification.";
            
        } else {
            $retVal[self::RESULT_FIELD_NAME] = false;
            $retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
        }

        echo json_encode($retVal);
    }

	public function get_users_posts()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$posts = $this->Post_model->getPostInfo(
				array(
					'user_id' => $this->input->post('user_id'),
					'poster_profile_type' => $this->input->post('business'),
					'multi_pos' => 0,
					'is_active' => 1
				)
			);

			for ($i = 0; $i < count($posts); $i++) {
				if (intval($posts[$i]['is_multi']) == 1) {
					$multiPosts = $this->Post_model->getPostInfo(array('is_active' => 1, 'multi_group' => $posts[$i]['multi_group']));

					foreach ($multiPosts as $elementKey => $element) {
						foreach ($element as $valueKey => $value) {
							if ($valueKey == 'id' && $value == $posts[$i]['id']) {
								unset($multiPosts[$elementKey]);
							}
						}
					}

					$multiPosts = array_values($multiPosts);
					$posts[$i]["group_posts"] = $multiPosts;
				}
			}

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $posts;

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getpostcount()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$postsCount = $this->Post_model->getPostCounter(array('user_id' => $this->input->post('user_id'), 'poster_profile_type' => $this->input->post('is_business')));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $postsCount;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getfollowercount()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$followers = $this->LikeInfo_model->getFollowerCounter($this->input->post('follower_business_id'), $this->input->post('follower_user_id'));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getfollower()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$followers = $this->LikeInfo_model->getFollowers($this->input->post('follower_business_id'), $this->input->post('follower_user_id'));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getfollowcount()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$followers = $this->LikeInfo_model->getFollowCounter($this->input->post('follow_user_id'), $this->input->post('follow_business_id'));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getfollow()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$followers = $this->LikeInfo_model->getFollows($this->input->post('follow_user_id'), $this->input->post('follow_business_id'));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function addfollow()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
				'follow_user_id' => $this->input->post('follow_user_id'),
				'follower_user_id' => $this->input->post('follower_user_id'),
				'follower_business_id' => $this->input->post('follower_business_id'),
				'follow_business_id' => $this->input->post('follow_business_id')
			);
			$followers = $this->LikeInfo_model->insertNewLike($updateArray);

			$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

			$this->NotificationHistory_model->insertNewNotification(
				array(
					'user_id' => $this->input->post('follower_user_id'),
					'type' => 17,
					'related_id' => $users[0]['id'],
					'read_status' => 0,
					'send_status' => 0,
					'visible' => 1,
					'text' => " is now following you",
					'name' => $users[0]['user_name'],
					'profile_image' => $users[0]['pic_url'],
					'updated_at' => time(),
					'created_at' => time()
				)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function deletefollower()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
				'follow_user_id' => $this->input->post('follow_user_id'),
				'follower_user_id' => $this->input->post('follower_user_id')
			);
			$followers = $this->LikeInfo_model->removeFollow($updateArray);
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $followers;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function getTransactions()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$transactions = $this->UserTransaction_model->getTransactionHistory(array('user_id' => $this->input->post('user_id')));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $transactions;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_pp_Transactions()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->load->model('UserBraintreeTransaction_model');
			$transactions = $this->UserBraintreeTransaction_model->getTransactionHistory(array('user_id' => $this->input->post('user_id')));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $transactions;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function updateprofile()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$pic = "";
			if (!empty($_FILES['pic']['name'])) {
                //update here
				$pic = $this->fileUpload('profile_photos', 'profile_' . time(), 'pic');
			}

			$updateArray = array(
				'user_email' => $this->input->post('user_email'),
				'first_name' => $this->input->post('first_name'),
				'last_name' => $this->input->post('last_name'),
				'country'   => $this->input->post('country'),
                'latitude' => $this->input->post('lat'),
                'longitude' => $this->input->post('lng'),
                'post_search_region' => $this->input->post('range'),
				'gender' => $this->input->post('gender'),
				'birthday' => $this->input->post('birthday')
			);
			if ($pic != "") {
				$updateArray['pic_url'] = $pic;
			}

			$this->User_model->updateUserRecord(
				$updateArray,
				array('id' => $tokenVerifyResult['id'])
			);
			$profile = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $profile[0];
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function updatebio()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->User_model->updateUserRecord(
				array('description' => $this->input->post('bio')),
				array('id' => $tokenVerifyResult['id'])
			);
			$profile = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $profile[0];
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function generate_ephemeral_key()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		require_once('application/libraries/stripe-php/init.php');
		\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
		$customerToken = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
		//2019-10-17
		$key = \Stripe\EphemeralKey::create(
			["customer" => $customerToken[0]['stripe_customer_token']],
			["stripe_version" => "2019-10-17"]
		);
		$retVal["key"] = $key;
		echo json_encode($retVal);
	}

	public function add_subscription()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
			$customerToken = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

			$subscription = \Stripe\Subscription::create(
				[
					'customer' => $customerToken[0]['stripe_customer_token'],
					'items' => [
						[
							'plan' => 'plan_FUSAHhAO4rxF17',
						],
					],
					'expand' => ['latest_invoice.payment_intent'],
				]
			);

			$this->UserTransaction_model->insertNewTransaction(
				array(
					'user_id' => $tokenVerifyResult['id'],
					'transaction_id' => $subscription->id,
					'amount' => -$subscription->plan->amount,
					'created_at' => time()
				)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function like_notifications()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->LikeInfo_model->updateFollow(
				array('post_notifications' => $this->input->post('notifications')),
				array(
					'follow_user_id' => $this->input->post('follow_user_id'), 
                    'follower_user_id' => $this->input->post('follower_user_id')
				)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function has_like_notifications()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$notification = $this->LikeInfo_model->post_notifications(
				array('follow_user_id' => $this->input->post('follow_user_id'), 'follower_user_id' => $this->input->post('follower_user_id'))
			);

			$retVal[self::MESSAGE_FIELD_NAME] = $notification;
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_notification_token()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->User_model->updateUserRecord(
				array('push_token' => $this->input->post('push_token')),
				array('id' => $tokenVerifyResult['id'])
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_connect_account()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));

			$response = \Stripe\OAuth::token(
				[
					'grant_type' => 'authorization_code',
					'code' => $this->input->post('connect'),
				]
			);

			// Access the connected account id in the response
			$connected_account_id = $response->stripe_user_id;

			$this->User_model->updateUserRecord(
				array('stripe_connect_account' => $connected_account_id),
				array('id' => $tokenVerifyResult['id'])
			);

			$retVal[self::MESSAGE_FIELD_NAME] = $connected_account_id;
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function make_payment()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$chargeAmount = $this->input->post('amount');

			$fee = round((($chargeAmount / 100) * 5));

			$toUserID = $this->input->post('toUserId');

			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));

			$customerToken = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
			$touser = $this->User_model->getOnlyUser(array('id' => $toUserID));

			$token = \Stripe\Token::create(
				["customer" => $customerToken[0]['stripe_customer_token']],
				["stripe_account" => $touser[0]['stripe_connect_account']]
			);

			$charge = \Stripe\Charge::create(
				[
					"amount" => $chargeAmount,
					"currency" => "gbp",
					"source" => $token->id,
					"application_fee_amount" => $fee,
				],
				["stripe_account" => $touser[0]['stripe_connect_account']]
			);

			$this->UserTransaction_model->insertNewTransaction(
				array(
					'user_id' => $tokenVerifyResult['id'],
					'transaction_id' => $charge->id,
					'amount' => -$charge->amount,
					'post_id' => $this->input->post('postId'),
					'product_id' => $this->input->post('product_id'),
					'variation_id' => $this->input->post('variation_id'),
					'created_at' => time()
				)
			);

			$this->UserTransaction_model->insertNewTransaction(
				array(
					'user_id' => $toUserID,
					'transaction_id' => $charge->id,
					'amount' => $charge->amount - $charge->application_fee_amount,
					'post_id' => $this->input->post('postId'),
					'product_id' => $this->input->post('product_id'),
					'variation_id' => $this->input->post('variation_id'),
					'created_at' => time()
				)
			);
			
			$title = "";
			$related_id = 0;
			$type = 0;
			
			if (!is_null($this->input->post('postId')) && $this->input->post('postId') != 0) {
				$title = $this->Post_model->getPostDetail($this->input->post('postId'))["title"];
				$related_id = $this->input->post('product_id'); 
				$updateResult = $this->Post_model->updatePostContent(
					array(
						'is_sold' => 1
					),
					array('id' => $this->input->post('postId'), 'post_type' => 2)
				);
			}
			
			if (!is_null($this->input->post('product_id')) && $this->input->post('product_id') != 0) {
				$title = $this->Product_model->getProduct($this->input->post('product_id'))[0]["title"];
				$related_id = $this->input->post('postId'); 
				$this->Product_model->updateProduct(
					array(
						"stock_level" => $this->Product_model->getProduct($this->input->post('product_id'))[0]["stock_level"] - 1
					),
					array('id' => $this->input->post('id'))
				);
			}
			
			if (!is_null($this->input->post('variation_id')) && $this->input->post('variation_id') != 0) {
				
				$related_id = $this->input->post('variation_id'); 
			}

			$postContent = $this->Post_model->getPostDetail($this->input->post('postId'));

			$this->NotificationHistory_model->insertNewNotification(
				array(
					'user_id' => $toUserID,
					'type' => 6,
					'related_id' => $related_id,
					'read_status' => 0,
                    'send_status' => 0,
					'visible' => 1,
					'text' => "Bought " . $title,
					'name' => $customerToken[0]['user_name'],
					'profile_image' => $customerToken[0]['pic_url'],
					'updated_at' => time(),
					'created_at' => time()
				)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_payment()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$cardToken = $this->input->post('card_token');
			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));

			$customerToken = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

			if (count($customerToken) > 0) {
				$card = \Stripe\Customer::createSource(
					$customerToken[0]['stripe_customer_token'],
					[
						'source' => $cardToken,
					]
				);

                // get customer detail from stripe
				$customerDataFromStripe = \Stripe\Customer::retrieve($customerToken[0]['stripe_customer_token']);

				$cardInsId = $this->PaymentCard_model->insertNewCard(
					array(
						'kind' => $this->input->post('kind'),
						'title' => $this->input->post('title'),
						'card_id' => $card['id'],
						'card_number' => $this->input->post('card_number'),
						'is_primary' => 0,
						'user_id' => $tokenVerifyResult['id'],
						'updated_at' => time(),
						'created_at' => time()
					)
				);

				$this->PaymentCard_model->updateCardRecord(array('is_primary' => 1, 'updated_at' => time()), array('card_id' => $customerDataFromStripe['default_source']));

				$newCard = $this->PaymentCard_model->getCardsByArray(array('id' => $cardInsId[self::MESSAGE_FIELD_NAME]));
				$retVal[self::RESULT_FIELD_NAME] = true;
				if (count($newCard) == 0) {
					$retVal[self::MESSAGE_FIELD_NAME] = null;
				} else {
					$retVal[self::MESSAGE_FIELD_NAME] = $newCard[0];
				}
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_cards()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$cards = $this->PaymentCard_model->getCardsByArray(array('user_id' => $tokenVerifyResult['id']));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $cards;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function set_primary_card()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
			$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

			$cus = \Stripe\Customer::update(
				$users[0]['stripe_customer_token'],
				[
					'default_source' => $this->input->post('card_id'),
				]
			);

			$this->PaymentCard_model->updateCardRecord(array('is_primary' => 0, 'updated_at' => time()), array('user_id' => $tokenVerifyResult['id']));
			$this->PaymentCard_model->updateCardRecord(
				array('is_primary' => 1, 'updated_at' => time()),
				array('card_id' => $this->input->post('card_id'), 'user_id' => $tokenVerifyResult['id'])
			);
			$cards = $this->PaymentCard_model->getCardsByArray(array('user_id' => $tokenVerifyResult['id']));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $cards;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}
		echo json_encode($retVal);
	}

	public function remove_card()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			require_once('application/libraries/stripe-php/init.php');
			\Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
			$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

			\Stripe\Customer::deleteSource(
				$users[0]['stripe_customer_token'],
				$this->input->post('card_id')
			);

			$cus = \Stripe\Customer::retrieve($users[0]['stripe_customer_token']);
			$this->PaymentCard_model->removeCardRecord(array('user_id' => $tokenVerifyResult['id'], 'card_id' => $this->input->post('card_id')));
			$this->PaymentCard_model->updateCardRecord(array('is_primary' => 0, 'updated_at' => time()), array('user_id' => $tokenVerifyResult['id']));
			$this->PaymentCard_model->updateCardRecord(
				array('is_primary' => 1, 'updated_at' => time()),
				array('card_id' => $cus->default_source, 'user_id' => $tokenVerifyResult['id'])
			);
			$cards = $this->PaymentCard_model->getCardsByArray(array('user_id' => $tokenVerifyResult['id']));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $cards;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_social()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$type = $this->input->post('type');
			if (count($this->UserSocial_model->getUserTypeSocials($tokenVerifyResult['id'], $type)) == 0) {
				$socialId = $this->UserSocial_model->insertNewUserSocial(
					array(
						'social_name' => $this->input->post('social_name'),
						'type' => $type,
						'user_id' => $tokenVerifyResult['id'],
						'created_at' => time()
					)
				);

				if ($socialId > 0) {
					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Added";
					$retVal[self::EXTRA_FIELD_NAME] = $socialId;
				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Failed to add";
				}
			} else {
				$this->UserSocial_model->updateUserSocial(
					array('social_name' => $this->input->post('social_name')),
					array(
						'type' => $type,
						'user_id' => $tokenVerifyResult['id']
					)
				);
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Updated";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_social()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
				'social_name' => $this->input->post('social_name'),
				'type' => $this->input->post('type'),
				'user_id' => $tokenVerifyResult['id'],
				'created_at' => time()
			);

			$this->UserSocial_model->updateUserSocial(
				$updateArray,
				array('id' => $this->input->post('id'))
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Social Updated";
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_socials()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$files = $this->UserSocial_model->getUserSocials($this->input->post('user_id'));
			if (count($files) == 0) {
				$retVal[self::EXTRA_FIELD_NAME] = null;
			} else {
				$retVal[self::EXTRA_FIELD_NAME] = $files;
			}
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function remove_social()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->UserSocial_model->removeUserSocials(
				array(
					'user_id' => $tokenVerifyResult['id'],
					'type' => $this->input->post('type')
				)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Social Removed";
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function truncateUserSocials()
	{
		$retVal = array();
		$this->UserSocial_model->truncateUserSocials();
		$retVal[self::RESULT_FIELD_NAME] = true;
		$retVal[self::MESSAGE_FIELD_NAME] = "Social Truncated";
		echo json_encode($retVal);
	}

	public function get_service()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$serviceId = $this->input->post('service_id');

			if (!empty($serviceId)) {
				$files = $this->UserService_model->getServiceInfo($serviceId);

				if (count($files) > 0) {
					foreach ($files as $key => $value){
						$tagids = $this->Tag_model->getServiceTags($value['id']);
						$tags = array();
						
						foreach ($tagids as $tagid) {
							$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
						}
						
						$files[$key]["tags"] = $tags;
					}

					$userInfos = $this->User_model->getOnlyUser(array('id' => $files[0]['user_id']));
					$files[0]['user'] = $userInfos;
					$files[0]["post_type"] = 3;

					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = "Success";
					$retVal[self::EXTRA_FIELD_NAME] = $files[0];
					
				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the service in our record.";
				}

			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "The service id is invalid.";
			}			

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function get_business_items(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$returnItems = array();
			$items = array();
			
			$files = $this->UserService_model->getServiceInfoList($this->input->post('user_id'));
			foreach ($files as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$files[$key]["tags"] = $tags;
                $files[$key]["post_type"] = 3;
			}
			
			$products = $this->Product_model->getUserProduct($this->input->post('user_id'),1);
			
			foreach ($products as $key => $value){
				$tagids = $this->Tag_model->getProductTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$products[$key]["tags"] = $tags;
                $products[$key]["post_type"] = 2;
			}
			
			$items = array_merge($files, $products);
			
			usort($items,function($first,$second){ return $first["created_at"] < $second["created_at"];});
			
			$returnItems["items"] = $items;

			$retVal[self::EXTRA_FIELD_NAME] = $returnItems;
			
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_services()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$files = $this->UserService_model->getServiceInfoList($this->input->post('user_id'));
			foreach ($files as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$files[$key]["tags"] = $tags;
				}
			if (count($files) == 0) {
				$retVal[self::EXTRA_FIELD_NAME] = null;
			} else {
				$retVal[self::EXTRA_FIELD_NAME] = $files;
			}
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_service_file()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$qualified_since_urls = array();
			$uploadFileName = "";
			if (array_key_exists('service_files', $_FILES)) {
				$uploadFileName = $this->fileUpload('service_files', 'service_' . time(), 'service_files');
	            //array_push($qualified_since_urls, $uploadFileName);
			}
			$serviceId = $this->UserServiceFiles_model->insertNewServiceFile(
				array(
					'type' => $this->input->post('type'),
		            //'file' => json_encode($qualified_since_urls),
					'file' => $uploadFileName,
					'company' => $this->input->post('company'),
					'reference' => $this->input->post('reference'),
					'expiry' => $this->input->post('expiry'),
					'user_id' => $tokenVerifyResult['id'],
					'created_at' => time()
				)
			);
			$retVal[self::EXTRA_FIELD_NAME] = null;
			if ($serviceId > 0) {
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Added";
				$retVal[self::EXTRA_FIELD_NAME] = $serviceId;
			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Failed to add new file.";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function delete_service_file()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$service_id = $this->input->post('id');
			$service_infos = $this->UserServiceFiles_model->getServiceFile($service_id);
			if (count($service_infos) > 0) {
				$service = $service_infos[0];
				$json_since_url = json_decode($service['file']);
				
				if(!empty($json_since_url)){
					foreach ($json_since_url as $fileURL) {
						unlink($fileURL);
					}
				}

				$this->UserServiceFiles_model->removeServiceFile(array('id' => $service_id));

				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Service File Removed";
			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Service File Requested";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_service_file()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
				'type' => $this->input->post('type'),
				'company' => $this->input->post('company'),
				'reference' => $this->input->post('reference'),
				'expiry' => $this->input->post('expiry'),
				'user_id' => $tokenVerifyResult['id'],
				'created_at' => time()
			);

			if (array_key_exists('service_files', $_FILES)) {
				$uploadFileName = $this->fileUpload('service_files', 'service_' . time(), 'service_files');
				$updateArray['file'] = $uploadFileName;
			}

			// commented by YueXi
			/*
			$qualified_since_urls = array();
			if (array_key_exists('service_files', $_FILES)) {
				$uploadFileName = $this->fileUpload('service_files', 'service_' . time(), 'service_files');
				array_push($qualified_since_urls, $uploadFileName);
			}
			$updateArray = array(
				'type' => $this->input->post('type'),
				'company' => $this->input->post('company'),
				'reference' => $this->input->post('reference'),
				'expiry' => $this->input->post('expiry'),
				'user_id' => $tokenVerifyResult['id'],
				'created_at' => time()
			);

			if (count($qualified_since_urls) > 0) {
				$updateArray['file'] = json_encode($qualified_since_urls);
			}
			*/
			$this->UserServiceFiles_model->updateServiceFileRecord(
				$updateArray,
				array('id' => $this->input->post('id'))
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Service File Updated";
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_service_files()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$files = $this->UserServiceFiles_model->getServiceFileList($tokenVerifyResult['id']);
			if (count($files) == 0) {
				$retVal[self::EXTRA_FIELD_NAME] = null;
			} else {
				$retVal[self::EXTRA_FIELD_NAME] = $files;
			}
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function add_product(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$uploadFileName = "";
			if (array_key_exists('image', $_FILES)) {
				$uploadFileName = $this->fileUpload('image', 'product_' . time(), 'image');
			}

			$productId = $this->Product_model->insertNewProduct(
				array(
					'poster_profile_type' => $this->input->post('poster_profile_type'),
					'media_type' => $this->input->post('media_type'),
					'title' => $this->input->post('title'),
					'brand' => $this->input->post('brand'),
					'user_id' => $tokenVerifyResult['id'],
					'price' => $this->input->post("price"),
					'description' => $this->input->post("description"),
					'category_title' => $this->input->post("category_title"),
					'is_deposit_required' => $this->input->post("is_deposit_required"),
					'deposit' => $this->input->post("deposit"),
					'lat' => $this->input->post("lat"),
					'lng' => $this->input->post("lng"),
					'item_title' => $this->input->post("item_title"),
					'size_title' => $this->input->post("size_title"),
					'payment_options' => $this->input->post("payment_options"),
					'location_id' => $this->input->post("location_id"),
					'delivery_option' => $this->input->post("delivery_option"),
                    'delivery_cost' => $this->input->post("delivery_cost"),
					'post_brand' => $this->input->post("brand"),
					'post_item' => $this->input->post("item_title"),
					'post_condition' => $this->input->post("post_condition"),
					'post_size' => $this->input->post("size_title"),
					'post_location' => $this->input->post("location_id"), 					
					'is_multi' => $this->input->post("is_multi"),
					'multi_pos' => $this->input->post("multi_pos"),
					'multi_group' => $this->input->post("multi_group"),
					'image' => $uploadFileName,
					'stock_level' => $this->input->post("stock_level"),
					'created_at' => time(),                                  
					'updated_at' => time()
				)
			);
			$retVal[self::EXTRA_FIELD_NAME] = null;
			
			if ($productId > 0) {
			
				$productAttributes = $this->input->post("attributes");
				
				if (!empty($productAttributes)) {
					$attributes = json_decode($productAttributes, true);
																
					if (!empty($attributes)) {
						for ($x = 0; $x < count($attributes); $x++) {
							$pAttributeId = $this->Product_model->insertNewProductAttribute( array (
								"product_id" => $productId,
								"value" => $attributes[$x]["attribute_name"],
								"created_at" => time(),
								"updated_at" => time()
							));
							
							$attributes[$x]["id"] = $pAttributeId;
						}
												
						$processedAttributes = array();
						
						foreach ($attributes as $attribute) {
							$processedAttributes[$attribute["id"]] = explode(",", $attribute["values"]);
						}
												
						$possibleConbinations = $this->cartesian($processedAttributes);
						
						foreach ($possibleConbinations as $conbination){
							$variationAttibutes = array();
							
							$title = "Variant";
							
							foreach($conbination as $key => $value) {
								$title .= " - " . $value;
								$variationAttibutes[] = array("id" => $key, "value" =>$value);
							}
							
							$this->Product_model->insertNewProductVariation(array(
								"product_id" => $productId,
								"title" => $title,
								"created_at" => time(),
								"updated_at" => time()
							), $variationAttibutes);
						} 							
					}
				}
			
				if (!empty($_FILES)) {
					for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
						$_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
						$_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
						$_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
						$_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
						$_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

						$uploadFileName = $this->fileUpload('post', 'post' . time(), 'post_img');
						$this->Product_model->insertNewImage(array('product_id' => $productId, 'path' => $uploadFileName, 'created_at' => time()));
					}
				}

				if ($this->input->post('post_img_uris') != null) {
					$uriList = $this->input->post('post_img_uris');
					$uriArray = explode(",", $uriList);

					foreach ($uriArray as $uri) {
						$this->Product_model->insertNewImage(array('product_id' => $productId, 'path' => str_replace(' ', '', $uri), 'created_at' => time()));
					}
				}
				
				if ($this->input->post("make_post") == 1){
					$multi_group = 0;
                    $insResult = 0;

					if ($this->input->post('is_multi') == "1") {
						if ($this->Post_model->isCurrentlyUploadingMultiGroup($tokenVerifyResult['id'])) {
							$multi_group = $this->Post_model->getCurrentMultiGroup($tokenVerifyResult['id']);
						} else {
							$multi_group = $this->Post_model->getNextMultiGroup();
						}
                        
                        $insResult = $this->Post_model->insertNewPost(
                            array(
                                'user_id' => $tokenVerifyResult['id'],
                                'post_type' => 2,
                                'poster_profile_type' => $this->input->post('poster_profile_type'),
                                'media_type' => $this->input->post('media_type'),
                                'title' => $this->input->post('title'),
                                'description' => $this->input->post('description'),
                                'post_brand' => $this->input->post('brand'),
                                'price' => $this->input->post('price'),
                                'category_title' => $this->input->post('category_title'),
                                'post_condition' => $this->input->post('post_condition'),
                                'post_tags' => $this->input->post('post_tags'),
                                'post_item' => $this->input->post('item_title'),
                                'post_size' => $this->input->post('size_title'),
                                'payment_options' => $this->input->post('payment_options'),
                                'post_location' => $this->input->post('location_id'),
                                'delivery_option' => $this->input->post('delivery_option'),
                                'delivery_cost' => $this->input->post('delivery_cost'),
                                'is_deposit_required' => $this->input->post("is_deposit_required"),
                                'deposit' => $this->input->post("deposit"),
                                'lat' => $this->input->post("lat"),
                                'lng' => $this->input->post("lng"),
                                'product_id' => $productId,
                                'is_multi' => $this->input->post("is_multi"),
                                'multi_pos' => $this->input->post("multi_pos"),
                                'multi_group' => $multi_group,                                  
                                'updated_at' => time(),
                                'created_at' => time()
                            )
                        );
                        
					} else {                        
                        $insResult = $this->Post_model->insertNewPost(
                            array(
                                'user_id' => $tokenVerifyResult['id'],
                                'post_type' => 2,
                                'poster_profile_type' => $this->input->post('poster_profile_type'),
                                'media_type' => $this->input->post('media_type'),
                                'title' => $this->input->post('title'),
                                'description' => $this->input->post('description'),
                                'post_brand' => $this->input->post('brand'),
                                'price' => $this->input->post('price'),
                                'category_title' => $this->input->post('category_title'),
                                'post_condition' => $this->input->post('post_condition'),
                                'post_tags' => $this->input->post('post_tags'),
                                'post_item' => $this->input->post('item_title'),
                                'post_size' => $this->input->post('size_title'),
                                'payment_options' => $this->input->post('payment_options'),
                                'post_location' => $this->input->post('location_id'),
                                'delivery_option' => $this->input->post('delivery_option'),
                                'delivery_cost' => $this->input->post('delivery_cost'),                                
                                'is_deposit_required' => $this->input->post("is_deposit_required"),
                                'deposit' => $this->input->post("deposit"),
                                'lat' => $this->input->post("lat"),
                                'lng' => $this->input->post("lng"),
                                'product_id' => $productId, 
                                'updated_at' => time(),
                                'created_at' => time()
                            )
                        );                        
                    } 
			
				    if ($insResult > 0) {
					    if (!empty($_FILES)) {
						    for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
							    $_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
							    $_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
							    $_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
							    $_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
							    $_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

							    $uploadFileName = $this->fileUpload('post', 'post' . time() /*$_FILES['post_img']['name']*/, 'post_img');
							    $this->Post_model->insertNewImage(array('post_id' => $insResult, 'path' => $uploadFileName, 'created_at' => time()));
						    }
					    } else if ($this->input->post('post_img_uris') != null) {
						    $uriList = $this->input->post('post_img_uris');
						    $uriArray = explode(",", $uriList);

						    foreach ($uriArray as $uri) {
							    $this->Post_model->insertNewImage(array('post_id' => $insResult, 'path' => str_replace(' ', '', $uri), 'created_at' => time()));
						    }
					    }
					
					    $insertedPost = $this->Post_model->getPostInfo(array('id' => $insResult));
				    
					    $tagList = $this->input->post('tags');
					    $tags = explode(",", $tagList);
					    
					    foreach ($tags as $tagName){
						     $tag = $this->Tag_model->getTagName($tagName);
						     if (count($tag) > 0){
						 	    $this->Tag_model->insertPostTag(array(
						 		    "post_id" => $insResult,
						 		    "tag_id" => $tag[0]["id"],
								    'created_at' => time()
						 	    ));
						     } else {
						 	    $tagId = $this->Tag_model->insertNewTag(
								    array(
									    'tag' => $tagName,
									    'created_at' => time()
								    )
							    );
							    $this->Tag_model->insertPostTag(array(
						 		    "post_id" => $insResult,
						 		    "tag_id" => $tagId,
								    'created_at' => time()
						 	    ));
						     }
					    }
					
					    $users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
						
						/*
					    $followers = $this->LikeInfo_model->getFollows($tokenVerifyResult['id'], $this->input->post('profile_type'));

					    foreach ($followers as $follower) {
						    if ($follower['post_notifications'] == 1) {
							    $this->NotificationHistory_model->insertNewNotification(
								    array(
									    'user_id' => $follower['follow_user_id'],
									    'type' => 2,
									    'related_id' => $insertedPost[0],
									    'read_status' => 0,
                                        'send_status' => 0,
									    'visible' => 1,
									    'text' => "New post: " . $this->input->post('title'),
									    'name' => $users[0]['user_name'],
									    'profile_image' => $users[0]['pic_url'],
									    'updated_at' => time(),
									    'created_at' => time()
								    )
							    );
						    }
					    }
						*/
				    }
			    }  
					
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Added";

				$products = $this->Product_model->getProduct($productId);
				
				$tagList = $this->input->post('tags');
				$tags = explode(",", $tagList);
				
				foreach ($tags as $tagName){
						 $tag = $this->Tag_model->getTagName($tagName);
						 if (count($tag) > 0){
						 	$this->Tag_model->insertProductTag(array(
					 		"product_id" => $productId,
					 		"tag_id" => $tag[0]["id"],
							'created_at' => time()
					 	));
						 } else {
						 	$tagId = $this->Tag_model->insertNewTag(
								array(
									'tag' => $tagName,
									'created_at' => time()
								)
							);
							$this->Tag_model->insertProductTag(array(
						 		"product_id" => $productId,
						 		"tag_id" => $tagId,
								'created_at' => time()
						 	));
						 }
					}
					
				
				foreach ($products as $key => $value) {
					$tagids = $this->Tag_model->getProductTags($value['id']);
					$tags = array();
				
					foreach ($tagids as $tagid) {                         
						$tags[] = $this->Tag_model->getTag($tagid['tag_id'])[0];
					}
				
					$products[$key]["tags"] = $tags;
				}
				
				$retVal[self::EXTRA_FIELD_NAME] = $products[0];

				$followers = $this->LikeInfo_model->getFollowers('0', $tokenVerifyResult['id']);
				$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
				for ($i = 0; $i < count($followers); $i ++) {
					if ($followers[$i]['post_notifications'] == 1) {
						$this->NotificationHistory_model->insertNewNotification(
							array(
								'user_id' => $followers[$i]['follow_user_id'],
								'type' => 18,
								'related_id' => $productId,
								'read_status' => 0,
								'send_status' => 0,
								'visible' => 1,
								'text' =>  " has uploaded a new product",
								'name' => $users[0]['user_name'],
								'profile_image' => $users[0]['pic_url'],
								'updated_at' => time(),
								'created_at' => time()
							)
						);
					}
				}

			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Failed to add new product.";
			}

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function update_product(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
            $product_id = $this->input->post("id");
            
			$updateArray = array(
					'poster_profile_type' => $this->input->post('poster_profile_type'),
					'media_type' => $this->input->post('media_type'),
					'title' => $this->input->post('title'),
					'brand' => $this->input->post('brand'),
					'price' => $this->input->post("price"),
					'description' => $this->input->post("description"),
					'category_title' => $this->input->post("category_title"),
					'lat' => $this->input->post("lat"),
					'lng' => $this->input->post("lng"),
					'item_title' => $this->input->post("item_title"),
					'size_title' => $this->input->post("size_title"),
					'payment_options' => $this->input->post("payment_options"),
					'location_id' => $this->input->post("location_id"),
					'delivery_option' => $this->input->post("delivery_option"),
                    'delivery_cost' => $this->input->post("delivery_cost"),
					'post_brand' => $this->input->post("brand"),
					'post_item' => $this->input->post("item_title"),
					'post_condition' => $this->input->post("post_condition"),
					'post_size' => $this->input->post("size_title"),
					'post_location' => $this->input->post("location_id"),
					'stock_level' => $this->input->post("stock_level"),
					'updated_at' => time()
				);

			$this->Product_model->updateProduct(
				$updateArray,
				array('id' => $product_id)
			);
            
            $salesPosts = $this->Post_model->getPostInfo(array('product_id' => $product_id));
            
            for ($postIndex = 0; $postIndex < count($salesPosts); $postIndex ++) {
                $this->Post_model->updatePostContent(
                    array(
                        'poster_profile_type' => $this->input->post('poster_profile_type'),
                        'media_type' => $this->input->post('media_type'),
                        'title' => $this->input->post('title'),
                        'description' => $this->input->post('description'),
                        'post_brand' => $this->input->post('brand'),
                        'price' => $this->input->post('price'),
                        'category_title' => $this->input->post('category_title'),
                        'post_condition' => $this->input->post('post_condition'),
                        'post_tags' => $this->input->post('post_tags'),
                        'post_item' => $this->input->post('item_title'),
                        'post_size' => $this->input->post('size_title'),
                        'payment_options' => $this->input->post('payment_options'),
                        'post_location' => $this->input->post('location_id'),
                        'delivery_option' => $this->input->post('delivery_option'),
                        'delivery_cost' => $this->input->post('delivery_cost'),
                        'lat' => $this->input->post("lat"),
                        'lng' => $this->input->post("lng"), 
                        'updated_at' => time()
                    ),
                    array('id' => $salesPosts[$postIndex]['id'])
                );
            }
            
            if ($this->input->post('post_img_uris') != null) {
                $uriList = $this->input->post('post_img_uris');
                $uriArray = explode(",", $uriList);
                
                if (count(array_filter($uriArray, function ($k){ return $k != "data"; })) != count($uriArray)) {
                    // 1 - user replaced all images or the video
                    // 2 - user partially updadted images  
                    $this->Product_model->removePostImg(array('product_id' => $product_id));
                    
                    if (!empty($_FILES)) {
                        for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
                            $_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
                            $_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
                            $_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
                            $_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
                            $_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

                            $uploadFileName = $this->fileUpload('post', 'post' . time(), 'post_img');
                            $dataIndex = array_search("data", $uriArray);   
                            if ($dataIndex !== false) {
                                $uriArray = array_replace($uriArray, array($dataIndex => $uploadFileName));
                            }                    
                        }
                    }
                    
                    foreach ($uriArray as $uri) {
                        if (!empty($uri)) {
                            $this->Product_model->insertNewImage(array('product_id' => $product_id, 'path' => $uri, 'created_at' => time()));
                        }
                    }
                    
                    for ($postIndex = 0; $postIndex < count($salesPosts); $postIndex ++) {
                        $this->Post_model->removePostImg(array('post_id' => $salesPosts[$postIndex]['id']));
                        
                        foreach ($uriArray as $uri) {
                            if (!empty($uri)) {
                                $this->Post_model->insertNewImage(array('post_id' => $salesPosts[$postIndex]['id'], 'path' => $uri, 'created_at' => time()));
                            }
                        }
                    }
                    
                } else {
                     // check if the user only delete few images                           
                    $imagesCnt = count($this->Product_model->getPostImage(array('product_id' => $product_id)));
                    if ($imagesCnt != count($uriArray)) {
                        $this->Product_model->removePostImg(array('product_id' => $product_id));
                        
                        foreach ($uriArray as $uri) {
                            if (!empty($uri)) {
                                $this->Product_model->insertNewImage(array('product_id' => $product_id, 'path' => $uri, 'created_at' => time()));
                            }
                        }
                        
                        for ($postIndex = 0; $postIndex < count($salesPosts); $postIndex ++) {
                            $this->Post_model->removePostImg(array('post_id' => $salesPosts[$postIndex]['id']));
                            
                            foreach ($uriArray as $uri) {
                                if (!empty($uri)) {
                                    $this->Post_model->insertNewImage(array('post_id' => $salesPosts[$postIndex]['id'], 'path' => $uri, 'created_at' => time()));
                                }
                            }
                        }
                    }
                }                
            }
			
			$this->Tag_model->removeProductTag(array("product_id" => $product_id));
			
			$tagList = $this->input->post('tags');
			$tags = explode(",", $tagList);
             				
			foreach ($tags as $tagId){
				 $tag = $this->Tag_model->getTag($tagId);
				 if (count($tag) > 0){
					$this->Tag_model->insertProductTag(array(
					 	"product_id" => $product_id,
					 	"tag_id" => $tag[0]["id"],
						'created_at' => time()
					));
				 }
			}
            
            for ($postIndex = 0; $postIndex < count($salesPosts); $postIndex ++) {
                 $this->Tag_model->removePostTag(array("post_id" => $salesPosts[$postIndex]['id']));
                 
                 foreach ($tags as $tagId){
                     $tag = $this->Tag_model->getTag($tagId);
                     if (count($tag) > 0){
                        $this->Tag_model->insertProductTag(array(
                             'post_id' => $salesPosts[$postIndex]['id'],
                             'tag_id' => $tag[0]["id"],
                             'created_at' => time()
                        ));
                     }
                }
            }
            
            $products = $this->Product_model->getProduct($product_id);
            foreach ($products as $key => $value){
                $tagids = $this->Tag_model->getProductTags($value['id']);
                $tags = array();
                
                foreach ($tagids as $tagid) {
                    $tags[] = $this->Tag_model->getTag($tagid['tag_id']);
                }
                
                $products[$key]["tags"] = $tags;
            }
            
            $products[0]['post_type'] = 2;  // product - set manually for mobile
            
			$retVal[self::RESULT_FIELD_NAME] = true;
            $retVal[self::MESSAGE_FIELD_NAME] = "Successfully updated";
            $retVal[self::EXTRA_FIELD_NAME] = $products[0];
            
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function get_product(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$productId = $this->input->post('product_id');
			if (!empty($productId)) {
				$products = $this->Product_model->getProduct($this->input->post('product_id'));
			
				if (count($products) > 0) {
					foreach ($products as $key => $value) {
						$tagids = $this->Tag_model->getProductTags($value['id']);
						$tags = array();
						
						foreach ($tagids as $tagid) {
							$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
						}
						
						$products[$key]["tags"] = $tags;
					}
	
					$userInfos = $this->User_model->getOnlyUser(array('id' => $products[0]['user_id']));
					$products[0]['user'] = $userInfos;
					$products[0]["post_type"] = 2;

					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = "Success";
					$retVal[self::EXTRA_FIELD_NAME] = $products[0];

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
				}

			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "The product id is invalid.";
			}
			
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function get_user_products(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$products = $this->Product_model->getUserProduct($this->input->post('user_id'),$this->input->post('is_business'));
			
			foreach ($products as $key => $value){
				$tagids = $this->Tag_model->getProductTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$products[$key]["tags"] = $tags;
			}

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $products;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}	
	
	public function delete_product() {
		$verifyTokenResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();
		if ($verifyTokenResult[self::RESULT_FIELD_NAME]) {
			$productId = $this->input->post('id');

			if (empty($productId)) {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "The product id is invalid.";

			} else {
				$products = $this->Product_model->getProduct($productId);
				if (count($products) > 0) {
					$setArray = array(
						'is_active' => 2,					// blocked
						'status_reason' => "User deleted",
						'updated_at' => time(),
					);
		
					$whereArray = array('id' => $productId);
		
					$this->Product_model->updateProduct($setArray, $whereArray);

					// update the relevant posts
					$posts = $this->Post_model->getPostInfo(array('product_id' => $productId, 'post_type' => 2));
					for ($postIndex = 0; $postIndex < count($posts); $postIndex++) {
						$this->Post_model->updatePostContent(
							 array(
								'is_active' => 2,
								'status_reason' => "User deleted",
								'updated_at' => time(),
							),
							array('id' => $posts[$postIndex]['id'])
						);
					}
		
					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = "The product has been deleted successfully.";

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
				}
			}
			
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credentials";
		}

		echo json_encode($retVal);
	}

	// public function remove_product(){
	// 	$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
	// 	$retVal = array();
	// 	if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
	// 		$this->Product_model->removeProduct(array('id' => $this->input->post('product_id')));

	// 		$retVal[self::RESULT_FIELD_NAME] = true;
	// 		$retVal[self::MESSAGE_FIELD_NAME] = "Successfully deleted";
			
	// 	} else {
	// 		$retVal[self::RESULT_FIELD_NAME] = false;
	// 		$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
	// 	}

	// 	echo json_encode($retVal);
	// }
	
	public function update_variant_product(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
					'stock_level' => $this->input->post('stock_level'),
					'title' => $this->input->post('title'),
					'price' => $this->input->post('price'),
					'updated_at' => time()
				);

			$this->Product_model->updateProductVariation(
				$updateArray,
				array('id' => $this->input->post('id'))
			); 

			$tag = $this->Product_model->getProductVariation( $this->input->post('id'));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $tag;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function delete_variant_product(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$this->Product_model->removeProductVariation(array('id' => $this->input->post('variant_id')));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Successfully deleted";
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}  
	
	public function get_tags(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			// $tags = $this->Tag_model->getAllTags();
            
            $tags = $this->UserTag_model->getUserTags(array('user_id' => $tokenVerifyResult['id']));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::EXTRA_FIELD_NAME] = $tags;
            
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
	
	public function get_tag(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$tag = $this->Tag_model->getTag($this->input->post('tag_id'));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $tag;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
    
    public function add_tag() {
        $tokenVerifyResult = $this->verificationToken($this->input->post('token'));
        
        $retVal = array();        
        if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
            $tagId = $this->UserTag_model->insertNewTag(
                array(
                    'user_id' => $tokenVerifyResult['id'],
                    'name' => $this->input->post('tag_name'),
                    'created_at' => time()
                )
            );
            
            if ($tagId > 0) {
                $retVal[self::RESULT_FIELD_NAME] = true;
                $retVal[self::MESSAGE_FIELD_NAME] = "The tag has been added successfully.";
                $tags = $this->UserTag_model->getTag($tagId);                
                $retVal[self::EXTRA_FIELD_NAME] = $tags[0];
                
            } else {
                $retVal[self::RESULT_FIELD_NAME] = false;
                $retVal[self::MESSAGE_FIELD_NAME] = "It's been failed to add the new tag.";
            }
            
        } else {
            $retVal[self::RESULT_FIELD_NAME] = false;
            $retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
        }

        echo json_encode($retVal);
    }
    
    public function delete_tag() {
        $tokenVerifyResult = $this->verificationToken($this->input->post('token'));
        
        $retVal = array();        
        if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
            $this->UserTag_model->removeTag(array('id' => $this->input->post('tag_id')));
            
            $retVal[self::RESULT_FIELD_NAME] = true;
            $retVal[self::MESSAGE_FIELD_NAME] = "The tag has been deleted successfully.";
            
        } else {
            $retVal[self::RESULT_FIELD_NAME] = false;
            $retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
        }

        echo json_encode($retVal);
    }
	
//	public function add_tag(){
//		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
//		$retVal = array();
//		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
//			$tagId = $this->Tag_model->insertNewTag(
//				array(
//					'tag' => $this->input->post('tag'),
//					'created_at' => time()
//				)
//			);
//			$retVal[self::EXTRA_FIELD_NAME] = null;
//			if ($tagId > 0) {
//				$retVal[self::RESULT_FIELD_NAME] = true;
//				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Added";
//				$products = $this->Tag_model->getTag($tagId);
//				$retVal[self::EXTRA_FIELD_NAME] = $products[0];
//			} else {
//				$retVal[self::RESULT_FIELD_NAME] = false;
//				$retVal[self::MESSAGE_FIELD_NAME] = "Failed to add new tag.";
//			}
//		} else {
//			$retVal[self::RESULT_FIELD_NAME] = false;
//			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
//		}

//		echo json_encode($retVal);
//	}
	
	public function update_tag(){
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
					'tag' => $this->input->post('tag'),
					'created_at' => time()
				);

			$this->Tag_model->updateTag(
				$updateArray,
				array('id' => $this->input->post('id'))
			);

			$tag = $this->Tag_model->getTag($this->input->post($this->input->post('id')));

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $tag;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}
			
	public function add_service()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {

			$serviceId = $this->UserService_model->insertNewServiceInfo(
				array(
					'poster_profile_type' => $this->input->post('poster_profile_type'),
					'media_type' => $this->input->post('media_type'),
					'title' => $this->input->post('title'),
					'brand' => $this->input->post('brand'),
					'is_deposit_required' => $this->input->post('is_deposit_required'),
					'user_id' => $tokenVerifyResult['id'],
					'deposit_amount' => $this->input->post('deposit_amount'),
					'insurance_id' => $this->input->post('insurance_id'),
					'qualification_id' => $this->input->post('qualification_id'),
					'cancellations' => $this->input->post("cancellations"),
					'price' => $this->input->post("price"),
					'duration' => $this->input->post('duration'),
					'description' => $this->input->post("description"),
					'category_title' => $this->input->post("category_title"),
					'lat' => $this->input->post("lat"),
					'lng' => $this->input->post("lng"),
					'item_title' => $this->input->post("item_title"),
					'size_title' => $this->input->post("size_title"),
					'payment_options' => $this->input->post("payment_options"),
					'location_id' => $this->input->post("location_id"),
					'delivery_option' => $this->input->post("delivery_option"),                    
                    'delivery_cost' => $this->input->post("delivery_cost"),
					'post_brand' => $this->input->post("brand"),
					'post_item' => $this->input->post("item_title"),
					'post_condition' => $this->input->post("post_condition"),
					'post_size' => $this->input->post("size_title"),
					'post_location' => $this->input->post("location_id"),
					'created_at' => time(),
					'updated_at' => time()
				)
			);
			$retVal[self::EXTRA_FIELD_NAME] = null;
			if ($serviceId > 0) {			
				if (!empty($_FILES)) {
					for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
						$_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
						$_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
						$_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
						$_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
						$_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

						$uploadFileName = $this->fileUpload('post', 'post' . time(), 'post_img');
						$this->UserService_model->insertNewImage(array('service_id' => $serviceId, 'path' => $uploadFileName, 'created_at' => time()));
					}
				}

				if ($this->input->post('post_img_uris') != null) {
					$uriList = $this->input->post('post_img_uris');
					$uriArray = explode(",", $uriList);

					foreach ($uriArray as $uri) {
						$this->UserService_model->insertNewImage(array('service_id' => $serviceId, 'path' => str_replace(' ', '', $uri), 'created_at' => time()));
					}
				}
					
				if ($this->input->post("make_post") == 1) {
					$insResult = $this->Post_model->insertNewPost(
						array(
							'user_id' => $tokenVerifyResult['id'],
							'post_type' => 3,
							'poster_profile_type' => $this->input->post('poster_profile_type'),
							'media_type' => $this->input->post('media_type'),
							'title' => $this->input->post('title'),
							'description' => $this->input->post('description'),
							'post_brand' => $this->input->post('brand'),
							'price' => $this->input->post('price'),
							'duration' => $this->input->post('duration'),
							'category_title' => $this->input->post('category_title'), 
							'post_condition' => $this->input->post('post_condition'),
							'post_tags' => $this->input->post('post_tags'),
							'post_item' => $this->input->post('item_title'),
							'post_size' => $this->input->post('size_title'),
							'payment_options' => $this->input->post('payment_options'),
							'post_location' => $this->input->post('location_id'),
							'delivery_option' => $this->input->post('delivery_option'),
							'delivery_cost' => $this->input->post('delivery_cost'),
							'is_deposit_required' => $this->input->post('is_deposit_required'),
							'deposit' => $this->input->post("deposit_amount"),
							'lat' => $this->input->post("lat"),
							'lng' => $this->input->post("lng"),
							'service_id' => $serviceId,       						
							'insurance_id' => $this->input->post('insurance_id'),
							'qualification_id' => $this->input->post('qualification_id'),
							'cancellations' => $this->input->post("cancellations"),
							'updated_at' => time(),
							'created_at' => time()
						)
					);
			
					if ($insResult > 0) {
						if (!empty($_FILES)) {
							for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
								$_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
								$_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
								$_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
								$_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
								$_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

								$uploadFileName = $this->fileUpload('post', 'post' . time() /*$_FILES['post_img']['name']*/, 'post_img');
								$this->Post_model->insertNewImage(array('post_id' => $insResult, 'path' => $uploadFileName, 'created_at' => time()));
							}

						} else if ($this->input->post('post_img_uris') != null) {
							$uriList = $this->input->post('post_img_uris');
							$uriArray = explode(",", $uriList);

							foreach ($uriArray as $uri) {
								$this->Post_model->insertNewImage(array('post_id' => $insResult, 'path' => str_replace(' ', '', $uri), 'created_at' => time()));
							}
						}
						
						$insertedPost = $this->Post_model->getPostInfo(array('id' => $insResult));
					
						$tagList = $this->input->post('tags');
						$tags = explode(",", $tagList);
						
						foreach ($tags as $tagName){
							$tag = $this->Tag_model->getTagName($tagName);
							if (count($tag) > 0){
								$this->Tag_model->insertPostTag(array(
									"post_id" => $insResult,
									"tag_id" => $tag[0]["id"],
									'created_at' => time()
								));
							} else {
								$tagId = $this->Tag_model->insertNewTag(
									array(
										'tag' => $tagName,
										'created_at' => time()
									)
								);
								$this->Tag_model->insertPostTag(array(
									"post_id" => $insResult,
									"tag_id" => $tagId,
									'created_at' => time()
								));
							}
						}
						
						$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

						// $followers = $this->LikeInfo_model->getFollows($tokenVerifyResult['id'], $this->input->post('profile_type'));

						// foreach ($followers as $follower) {
						// 	if ($follower['post_notifications'] == 1) {
						// 		$this->NotificationHistory_model->insertNewNotification(
						// 			array(
						// 				'user_id' => $follower['follow_user_id'],
						// 				'type' => 2,
						// 				'related_id' => $insertedPost[0],
						// 				'read_status' => 0,
						// 				'send_status' => 0,
						// 				'visible' => 1,
						// 				'text' => "New post: " . $this->input->post('title'),
						// 				'name' => $users[0]['user_name'],
						// 				'profile_image' => $users[0]['pic_url'],
						// 				'updated_at' => time(),
						// 				'created_at' => time()
						// 			)
						// 		);
						// 	}
						// }
					}
				}
			
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Added";
				$services = $this->UserService_model->getServiceInfo($serviceId);
				
				$tagList = $this->input->post('tags');
				$tags = explode(",", $tagList);
				
				foreach ($tags as $tagName) {
					$tag = $this->Tag_model->getTagName($tagName);
					if (count($tag) > 0){
						$this->Tag_model->insertServiceTag(array(
							"service_id" => $serviceId,
							"tag_id" => $tag[0]["id"],
							'created_at' => time()
						));
					} else {
						$tagId = $this->Tag_model->insertNewTag(
							array(
								'tag' => $tagName,
								'created_at' => time()
							)
						);
						$this->Tag_model->insertServiceTag(array(
							"service_id" => $serviceId,
							"tag_id" => $tagId,
							'created_at' => time()
						));
					}
				}				
				
				foreach ($services as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$services[$key]["tags"] = $tags;
				}
				
				
				$retVal[self::EXTRA_FIELD_NAME] = $services[0];

				$followers = $this->LikeInfo_model->getFollowers('0', $tokenVerifyResult['id']);
				$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

				for ($i = 0; $i < count($followers); $i ++) {
					if ($followers[$i]['post_notifications'] == 1) {
						$this->NotificationHistory_model->insertNewNotification(
							array(
								'user_id' => $followers[$i]['follow_user_id'],
								'type' => 19,
								'related_id' => $serviceId,
								'read_status' => 0,
								'send_status' => 0,
								'visible' => 1,
								'text' =>  " has uploaded a new service",
								'name' => $users[0]['user_name'],
								'profile_image' => $users[0]['pic_url'],
								'updated_at' => time(),
								'created_at' => time()
							)
						);
					}					
				}

				$content = '
		<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;">You have added a new service to your business. This will be reviewed by the admin team before it can be approved as a valid service.</span></p>
		<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;"><b></b></span></p>';

				$subject = 'ATB Business account created';

				$this->User_model->sendUserEmail($users[0]["user_email"], $subject, $content);
			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Failed to add new service.";
			}

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_service()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
            $service_id = $this->input->post('id');
            
			$updateArray = array(
                    'user_id' => $tokenVerifyResult['id'],
					'poster_profile_type' => $this->input->post('poster_profile_type'),
					'media_type' => $this->input->post('media_type'),
					'title' => $this->input->post('title'),
					'brand' => $this->input->post('brand'),
                    'description' => $this->input->post("description"),
                    'price' => $this->input->post("price"),
					'duration' => $this->input->post('duration'),
					'is_deposit_required' => $this->input->post('is_deposit_required'),					
					'deposit_amount' => $this->input->post('deposit_amount'),
					'insurance_id' => $this->input->post('insurance_id'),
					'qualification_id' => $this->input->post('qualification_id'),
					'cancellations' => $this->input->post("cancellations"),					    					
					'category_title' => $this->input->post("category_title"),
                    'location_id' => $this->input->post("location_id"),
					'lat' => $this->input->post("lat"),
					'lng' => $this->input->post("lng"),
					'item_title' => $this->input->post("item_title"),
					'size_title' => $this->input->post("size_title"),
					'payment_options' => $this->input->post("payment_options"),					
					'delivery_option' => $this->input->post("delivery_option"),
                    'delivery_cost' => $this->input->post("delivery_cost"),
					'post_brand' => $this->input->post("brand"),
					'post_item' => $this->input->post("item_title"),
					'post_condition' => $this->input->post("post_condition"),
					'post_size' => $this->input->post("size_title"),
					'post_location' => $this->input->post("location_id"),
					'updated_at' => time()
				);

			$this->UserService_model->updateServiceRecord(
				$updateArray,
				array('id' => $service_id)
			);
            
            $servicePosts = $this->Post_model->getPostInfo(array('service_id' => $service_id));
            
            for ($postIndex = 0; $postIndex < count($servicePosts); $postIndex ++) {
                $this->Post_model->updatePostContent(
                    array(
                        'user_id' => $tokenVerifyResult['id'],
                        'poster_profile_type' => $this->input->post('poster_profile_type'),
                        'media_type' => $this->input->post('media_type'),
                        'title' => $this->input->post('title'),
                        'description' => $this->input->post('description'),
                        'price' => $this->input->post('price'),
						'duration' => $this->input->post('duration'),
                        'post_brand' => $this->input->post('brand'),  
                        'location_id' => $this->input->post("location_id"),                      
                        'category_title' => $this->input->post('category_title'),
                        'post_condition' => $this->input->post('post_condition'),
                        'post_tags' => $this->input->post('post_tags'),
                        'post_item' => $this->input->post('item_title'),
                        'post_size' => $this->input->post('size_title'),
                        'payment_options' => $this->input->post('payment_options'),
                        'post_location' => $this->input->post('location_id'),
                        'delivery_option' => $this->input->post('delivery_option'),
                        'delivery_cost' => $this->input->post('delivery_cost'),
                        'deposit' => $this->input->post("deposit_amount"),
                        "is_deposit_required" => $this->input->post("is_deposit_required"),
                        'lat' => $this->input->post("lat"),
                        'lng' => $this->input->post("lng"),
                        'insurance_id' => $this->input->post('insurance_id'),
                        'qualification_id' => $this->input->post('qualification_id'),
                        'cancellations' => $this->input->post('cancellations'), 
                        'updated_at' => time()
                    ),
                    array('id' => $servicePosts[$postIndex]['id'])
                );
            }
            
            if ($this->input->post('post_img_uris') != null) {
                $uriList = $this->input->post('post_img_uris');
                $uriArray = explode(",", $uriList);
                
                if (count(array_filter($uriArray, function ($k){ return $k != "data"; })) != count($uriArray)) {
                    // 1 - user replaced all images or the video
                    // 2 - user partially updadted images 
                    $this->UserService_model->removePostImg(array('service_id' => $service_id));
                    
                    if (!empty($_FILES)) {
                        for ($fIndex = 0; $fIndex < count($_FILES['post_imgs']['name']); $fIndex++) {
                            $_FILES['post_img']['name'] = $_FILES['post_imgs']['name'][$fIndex];
                            $_FILES['post_img']['type'] = $_FILES['post_imgs']['type'][$fIndex];
                            $_FILES['post_img']['tmp_name'] = $_FILES['post_imgs']['tmp_name'][$fIndex];
                            $_FILES['post_img']['error'] = $_FILES['post_imgs']['error'][$fIndex];
                            $_FILES['post_img']['size'] = $_FILES['post_imgs']['size'][$fIndex];

                            $uploadFileName = $this->fileUpload('post', 'post' . time(), 'post_img');
                            $dataIndex = array_search("data", $uriArray);   
                            if ($dataIndex !== false) {
                                $uriArray = array_replace($uriArray, array($dataIndex => $uploadFileName));
                            }                    
                        }
                    }
                    
                    foreach ($uriArray as $uri) {
                        if (!empty($uri)) {
                            $this->UserService_model->insertNewImage(array('service_id' => $service_id, 'path' => $uri, 'created_at' => time()));
                        }
                    }
                    
                    for ($postIndex = 0; $postIndex < count($servicePosts); $postIndex ++) {
                        $this->Post_model->removePostImg(array('post_id' => $servicePosts[$postIndex]['id']));
                        
                        foreach ($uriArray as $uri) {
                            if (!empty($uri)) {
                                $this->Post_model->insertNewImage(array('post_id' => $servicePosts[$postIndex]['id'], 'path' => $uri, 'created_at' => time()));
                            }
                        }
                    }
                } else {
                    // check if the user only delete few images                           
                    $imagesCnt = count($this->UserService_model->getPostImage(array('service_id' => $service_id)));
                    if ($imagesCnt != count($uriArray)) {
                        $this->UserService_model->removePostImg(array('service_id' => $service_id));
                        
                        foreach ($uriArray as $uri) {
                            if (!empty($uri)) {
                                $this->UserService_model->insertNewImage(array('service_id' => $service_id, 'path' => $uri, 'created_at' => time()));
                            }
                        }
                        
                        for ($postIndex = 0; $postIndex < count($servicePosts); $postIndex ++) {
                            $this->Post_model->removePostImg(array('post_id' => $servicePosts[$postIndex]['id']));
                            
                            foreach ($uriArray as $uri) {
                                if (!empty($uri)) {
                                    $this->Post_model->insertNewImage(array('post_id' => $servicePosts[$postIndex]['id'], 'path' => $uri, 'created_at' => time()));
                                }
                            }
                        }
                    }
                }               
            }
            
            $this->Tag_model->removeServiceTag(array("service_id" => $service_id));
            
            $tagList = $this->input->post('post_tags');
            $tags = explode(",", $tagList);
            foreach ($tags as $tagId){
                 $tag = $this->Tag_model->getTag($tagId);
                 if (count($tag) > 0){
                    $this->Tag_model->insertServiceTag(array(
                         'service_id' => $service_id,
                         'tag_id' => $tag[0]["id"],
                         'created_at' => time()
                    ));
                 }
            }
            
            for ($postIndex = 0; $postIndex < count($servicePosts); $postIndex ++) {
                $tag = $this->Tag_model->getTag($tagId);
                if (count($tag) > 0){
                    $this->Tag_model->insertPostTag(array(
                        'post_id' => $servicePosts[$postIndex]['id'],
                        'tag_id' => $tag[0]["id"],
                        'created_at' => time()
                    ));
                }
            }
            
            $services = $this->UserService_model->getServiceInfo($service_id);
            foreach ($services as $key => $value){
                $tagids = $this->Tag_model->getServiceTags($value['id']);
                $tags = array();
                
                foreach ($tagids as $tagid) {
                    $tags[] = $this->Tag_model->getTag($tagid['tag_id']);
                }
                
                $services[$key]["tags"] = $tags;
            }
            
            $services[0]["post_type"] = 3;
            			
			$retVal[self::RESULT_FIELD_NAME] = true;
            $retVal[self::MESSAGE_FIELD_NAME] = "Successfully updated";
            $retVal[self::EXTRA_FIELD_NAME] = $services[0];
			
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function delete_service() {
		$verifyTokenResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();
		if ($verifyTokenResult[self::RESULT_FIELD_NAME]) {
			$serviceId = $this->input->post('id');

			if (empty($serviceId)) {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "The service id is invalid.";

			} else {
				$services = $this->UserService_model->getServiceInfo($serviceId);
				if (count($services) > 0) {
					$setArray = array(
						'is_active' => 2,					// blocked
						'approval_reason' => "User deleted",
						'updated_at' => time(),
					);
		
					$whereArray = array('id' => $serviceId);
		
					$this->UserService_model->updateServiceRecord($setArray, $whereArray);

					// update the relevant posts
					$posts = $this->Post_model->getPostInfo(array('service_id' => $serviceId, 'post_type' => 3));
					for ($postIndex = 0; $postIndex < count($posts); $postIndex++) {
						$this->Post_model->updatePostContent(
							 array(
								'is_active' => 2,
								'status_reason' => "User deleted",
								'updated_at' => time(),
							),
							array('id' => $posts[$postIndex]['id'])
						);
					}
		
					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = "The service has been deleted successfully.";

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the service in our record.";
				}
			}
			
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credentials";
		}

		echo json_encode($retVal);
	}

	public function remove_service()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$service_id = $this->input->post('id');
			$service_infos = $this->UserService_model->getServiceInfo($service_id);
			if (count($service_infos) > 0) {
				$service = $service_infos[0];

				$this->UserService_model->removeCardRecord(array('id' => $service_id));
				$service_infos = $this->UserService_model->getServiceInfoList($tokenVerifyResult['id']);
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Service Removed";
				$retVal[self::EXTRA_FIELD_NAME] = $service_infos;

			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Service Requested";
			}

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function create_business_account()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$avatar = $this->fileUpload('business_avatars', 'business_' . time(), 'avatar');
			$businessId = $this->UserBusiness_model->insertNewBusinessInfo(
				array(
					'business_logo' => $avatar,
					'business_name' => $this->input->post('business_name'),
					'business_website' => $this->input->post('business_website'),
					'business_bio' =>  $this->input->post('business_bio'),
					'business_profile_name' => $this->input->post('business_profile_name'),
                    'timezone' => $this->input->post("timezone"),
					'updated_at' => time(),
					'created_at' => time(),
					'user_id' => $tokenVerifyResult['id']
				)
			);

			$retVal[self::EXTRA_FIELD_NAME] = null;
			if ($businessId > 0) {
				$this->User_model->updateUserRecord(array('account_type' => 1, 'updated_at' => time()), array('id' => $tokenVerifyResult['id']));
				
				for ($i = 0; $i < 7; $i++){
					$is_available = 1;
					if ($i == 5 || $i == 6){
						$is_available = 0;
					}
					$this->UserBusiness_model->insertBusinessWeekDay(
						array(
							'user_id' => $tokenVerifyResult['id'],
							'day' => $i,
							'is_available' => $is_available,
							'start' => "08:00:00",
							'end' => "17:00:00",
							'created_at' => time(),
							'updated_at' => time()
						)
					);
				}
				
                
                
                // subscribe user 
                // check if he is in 500, 4.99, else 7.99
				/*$allBusiness = $this->UserBusiness_model->getBusinessInfos();
                require_once('application/libraries/stripe-php/init.php');
                \Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
                if(count($allBusiness) > 500) {
                   $subscription =  \Stripe\Subscription::create([
                        "customer" => $users[0]['stripe_customer_token'],
                        "items" => [
                          [
                            "plan" => "business1",
                          ],
                        ]
                      ]);
                      
                }
                else {
                    $subscription = \Stripe\Subscription::create([
                        "customer" => $users[0]['stripe_customer_token'],
                        "items" => [
                          [
                            "plan" => "plan_FUSAHhAO4rxF17",
                          ],
                        ]
                      ]);
                }*/

																				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Successfully Upgraded";
				$business = $this->UserBusiness_model->getBusinessInfoById($businessId);
				$services = $this->UserService_model->getServiceInfoList($tokenVerifyResult['id']);
				foreach ($services as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$services[$key]["tags"] = $tags;
				}
				$business[0]['services'] = $services;
				$retVal[self::EXTRA_FIELD_NAME] = $business[0];
			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_business_account()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$avatar = "";
			if (!empty($_FILES['avatar']['name'])) {
                //update here
				$avatar = $this->fileUpload('business_avatars', 'business_' . time(), 'avatar');
			}

			$updateArray = array(
				'business_name' => $this->input->post('business_name'),
				'business_website' => $this->input->post('business_website'),
				'business_profile_name' => $this->input->post('business_profile_name'),
                'business_bio' =>  $this->input->post('business_bio'),
                'timezone' => $this->input->post("timezone"),
				'updated_at' => time()
			);

			if ($avatar != "") {
				$updateArray['business_logo'] = $avatar;
			}

			$this->UserBusiness_model->updateBusinessRecord(
				$updateArray,
				array('id' => $this->input->post('id'))
			);

			$business = $this->UserBusiness_model->getBusinessInfoById($this->input->post('id'));

			$services = $this->UserService_model->getServiceInfoList($tokenVerifyResult['id']);
			foreach ($services as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$services[$key]["tags"] = $tags;
				}
			$business[0]['services'] = $services;

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Succesfully updated";
			$retVal[self::EXTRA_FIELD_NAME] = $business[0];
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}
		echo json_encode($retVal);
	}

	public function read_business_account_from_id()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$business_model = $this->UserBusiness_model->getBusinessInfoById($this->input->post('business_id'));
			if (count($business_model) == 0) {
				$retVal[self::MESSAGE_FIELD_NAME] = null;
			} else {
				$business = $business_model[0];
				$services = $this->UserService_model->getServiceInfoList($business["user_id"]);
				foreach ($services as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$services[$key]["tags"] = $tags;
				}
				$business['services'] = $services;
				$retVal[self::MESSAGE_FIELD_NAME] = $business;
			}
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function read_business_account()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$business_model = $this->UserBusiness_model->getBusinessInfo($tokenVerifyResult['id']);
			if (count($business_model) == 0) {
				$retVal[self::MESSAGE_FIELD_NAME] = null;
			} else {
				$business = $business_model[0];
				$services = $this->UserService_model->getServiceInfoList($tokenVerifyResult['id']);
				foreach ($services as $key => $value){
				$tagids = $this->Tag_model->getServiceTags($value['id']);
				$tags = array();
				
				foreach ($tagids as $tagid) {
					$tags[] = $this->Tag_model->getTag($tagid['tag_id']);
				}
				
				$services[$key]["tags"] = $tags;
				}
				$business['services'] = $services;
				$retVal[self::MESSAGE_FIELD_NAME] = $business;
			}
			$retVal[self::RESULT_FIELD_NAME] = true;
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function update_business_bio()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$updateArray = array(
				'business_bio' => $this->input->post('business_bio'),
				'updated_at' => time()
			);

			$this->UserBusiness_model->updateBusinessRecord(
				$updateArray,
				array('id' => $this->input->post('id'))
			);

			$business = $this->UserBusiness_model->getBusinessInfoById($this->input->post('id'));
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $business[0];
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}
		echo json_encode($retVal);
	}

	public function addbusinessreviews()
	{
		$this->load->model('UserReview_model');

		$verifyTokenResult = $this->verificationToken($this->input->post('token'));
		$retVal = [];

		if ($verifyTokenResult[self::RESULT_FIELD_NAME]) {
			$insResult = $this->UserReview_model->insertNewReview(
				array(
					'user_id' => $verifyTokenResult['id'],
					'business_id' => $this->input->post('business_id'),
					'rating' => $this->input->post('rating'),
					'review' => $this->input->post('review'),
					'created_at' => time()
				)
			);

			$users = $this->User_model->getOnlyUser(array('id' => $verifyTokenResult['id']));
			
			$businessId = $this->input->post('business_id');
			$business = $this->UserBusiness_model->getBusinessInfoById($businessId)[0];

			$bookingId = $this->input->post('booking_id');

			if (!empty($bookingId)) {
				$bookings = $this->Booking_model->getBooking($bookingId);
				$services= $this->UserService_model->getServiceInfo($bookings[0]['service_id']);

				$this->NotificationHistory_model->insertNewNotification(
					array(
						'user_id' => $business['user_id'],
						'type' => 13,
						'related_id' => $business['user_id'],
						'read_status' => 0,
						'send_status' => 0,
						'visible' => 1,
						'text' => " has left you a rating for " . $services[0]['title'],
						'name' => $users[0]['user_name'],
						'profile_image' => $users[0]['pic_url'],
						'updated_at' => time(),
						'created_at' => time()
					)
				);

			} else {
				$this->NotificationHistory_model->insertNewNotification(
					array(
						'user_id' => $business['user_id'],
						'type' => 13,
						'related_id' => $business['user_id'],
						'read_status' => 0,
						'send_status' => 0,
						'visible' => 1,
						'text' => " has left you a rating",
						'name' => $users[0]['user_name'],
						'profile_image' => $users[0]['pic_url'],
						'updated_at' => time(),
						'created_at' => time()
					)
				);
			}

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Successfully published";

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
			$retVal[self::EXTRA_FIELD_NAME] = null;
		}

		echo json_encode($retVal);
	}

	public function getbusinessreview()
	{
		$this->load->model('UserReview_model');
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
        
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$reviews = $this->UserReview_model->getReviews(array('business_id' => $this->input->post('business_id')));
            
            foreach ($reviews as &$review) {
                $profile = $this->User_model->getUserProfileDTO($review['user_id']);
                $review['rater'] = $profile['profile'];
            }
            unset($review); // break the reference with the last review element
            
			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = $reviews;
            
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_pp_address()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$gateway = new Braintree\Gateway(
				[
					'environment' => $this->config->item('braintree_environment'),
					'merchantId' => $this->config->item('braintree_merchantId'),
					'publicKey' => $this->config->item('braintree_publicKey'),
					'privateKey' => $this->config->item('braintree_privateKey'),
				]
			);

			$paymentNonce = $this->input->post('paymentMethodNonce');
			$customerId = $this->input->post('customerId');

			$paymentMethod = $gateway->paymentMethod()->create(
				[
					'customerId' => $customerId,
					'paymentMethodNonce' => $paymentNonce
				]
			);

			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Payment Method Error!";

			if ($paymentMethod != null) {
				$paymentMethodCreateSuccess = $paymentMethod->success;
				if ($paymentMethodCreateSuccess) {
					$paymentMethodResult = $paymentMethod->paymentMethod;
					$paypal_address = $paymentMethodResult->email;

					$this->load->model('UserBraintree_model');
					$this->load->model('NotificationHistory_model');

					$userId = $tokenVerifyResult['id'];

					$updateResult = $this->UserBraintree_model->updateUserBraintreeCustomerId(
						array('receive_address' => $paypal_address, 'updated_at' => time()),
						array('user_id' => $userId)
					);
					$userInfo = $this->User_model->getOnlyUser(array('id' => $userId));

					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = $paypal_address;
				}
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Payment Method Error!";
		}

		echo json_encode($retVal);
	}

	public function make_pp_payment()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$bt_customer_id = $this->input->post('customerId');
			$toUserID = $this->input->post('toUserId');
			
			// no longer use the post whether to purchase a product or book a service
			$postID = $this->input->post('postId');				
			$productID = $this->input->post('product_id');
			$variantID = $this->input->post('variation_id');
			$delivery_option = $this->input->post('delivery_option');
			$quantity = $this->input->post('quantity');

			$serviceID = $this->input->post('serviceId');			
			$bookingID = $this->input->post('booking_id');
			
			$paymentNonce = $this->input->post('paymentNonce');
			$paymentMethod = $this->input->post('paymentMethod');
			$amount = $this->input->post('amount');
			$is_business = $this->input->post('is_business');
			
			$transactions = array();

			$gateway = new Braintree\Gateway(
				[
					'environment' => $this->config->item('braintree_environment'),
					'merchantId' => $this->config->item('braintree_merchantId'),
					'publicKey' => $this->config->item('braintree_publicKey'),
					'privateKey' => $this->config->item('braintree_privateKey'),
				]
			);

			$SaleTransaction = $gateway->transaction()->sale(
				[
					'amount' => $amount,
					'paymentMethodNonce' => $paymentNonce,
					'options' => [
						'submitForSettlement' => True
					]
				]
			);

			if ($SaleTransaction->success) {
				$userInfo = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

				$this->load->model('UserBraintree_model');
				$this->load->model('UserBraintreeTransaction_model');
				$this->load->model('NotificationHistory_model');
				$this->load->model('Post_model');

				$paymentSource = "";
				if ($paymentMethod == "Paypal") {
					$paypalInfo = $SaleTransaction->transaction->paypal;
					$paymentSource = $paypalInfo['payerEmail'];

				} else {
					$cardInfo = $SaleTransaction->transaction->creditCard;
					$paymentSource = $cardInfo['last4'];
				}

				$toUserBraintree = $this->UserBraintree_model->getUserBraintreeInfo(array('user_id' => $toUserID));

				if (count($toUserBraintree) > 0) {
					$fee = $amount * 0.9;
					$fee = floor($fee * 100) / 100;

					$payOut = new \PayPal\Api\Payout();
					$senderBatchHeader = new \PayPal\Api\PayoutSenderBatchHeader();
					$senderBatchHeader->setSenderBatchId(uniqid())->setEmailSubject("You have a payout");
					$senderItem = new \PayPal\Api\PayoutItem();

					$receiverEmail = $toUserBraintree[0]['receive_address'];

					if ($receiverEmail != "") {
						$senderItem->setRecipientType('Email')
							->setNote("")
							->setReceiver($toUserBraintree[0]['receive_address'])
							->setSenderItemId($postID . "_" . time())
							->setAmount(
								new \PayPal\Api\Currency(
									'{
                                        "value" : "' . $fee . '",
                                        "currency" : "GBP"
                                    }'
								)
							);

						$payOut->setSenderBatchHeader($senderBatchHeader)
							->addItem($senderItem);

						$apiContext = new \PayPal\Rest\ApiContext(
							new \PayPal\Auth\OAuthTokenCredential(
								$this->config->item('paypal_clientID'),
								$this->config->item('paypal_secret')
							)
						);

						$apiContext->setConfig(array('mode' => 'sandbox'));

						try {
							$payoutResult = $payOut->create(array(), $apiContext);
							
							$transactionArray = array(
									'user_id' => $toUserID,
									'from_to' => $tokenVerifyResult['id'],
									'transaction_id' => $payoutResult->batch_header->payout_batch_id,
									'transaction_type' => "Income",
									'amount' => $amount,
									'quantity' => $quantity,
									'payment_method' => $paymentMethod,
									'payment_source' => $paymentSource,
									'is_business' => $is_business,
									'created_at' => time()
								);
							
							if(!empty($variantID)){
								$transactionArray['purchase_type'] = "product_variant";
								$transactionArray['target_id'] = $variantID;

							} else if(!empty($productID)){
								$transactionArray['purchase_type'] = "product";
								$transactionArray['target_id'] = $productID;

							} else if(!empty($serviceID)){
								$transactionArray['purchase_type'] = "service";
								$transactionArray['target_id'] = $serviceID;

							} else if(!empty($bookingID)){
								$transactionArray['purchase_type'] = "booking";
								$transactionArray['target_id'] = $bookingID;
							} 

							// else if(!empty($postID)){
							// 	$transactionArray['purchase_type'] = "post";
							// 	$transactionArray['target_id'] = $postID;

							// }
							
							if (!empty($delivery_option)){
								$transactionArray['delivery_option'] = $delivery_option;
							}
								
							$transactionId = $this->UserBraintreeTransaction_model->insertNewTransaction($transactionArray);
							
							$transactions[] = $this->UserBraintreeTransaction_model->getTransaction($transactionId[MY_Controller::RESULT_FIELD_NAME]);
							
						} catch (Exception $ex) {}
					}
					
					$transactionArray = array(
						'user_id' => $tokenVerifyResult['id'],
						'from_to' => $toUserID,
						'transaction_id' => $SaleTransaction->transaction->id,
						'transaction_type' => "Sale",
						'amount' => -$amount,
						'quantity' => $quantity,
						'payment_method' => $paymentMethod,
						'payment_source' => $paymentSource,
						'is_business' => $is_business,
						'created_at' => time()
					);
					
					if(!empty($variantID)){
						$transactionArray['purchase_type'] = "product_variant";
						$transactionArray['target_id'] = $variantID;

					} else if(!empty($productID)){
						$transactionArray['purchase_type'] = "product";
						$transactionArray['target_id'] = $productID;

					} else if(!empty($serviceID)){
						$transactionArray['purchase_type'] = "service";
						$transactionArray['target_id'] = $serviceID;

					} else if(!empty($bookingID)){
						$transactionArray['purchase_type'] = "booking";
						$transactionArray['target_id'] = $bookingID;
					} 

					// else if(!empty($postID)){
					// 	$transactionArray['purchase_type'] = "post";
					// 	$transactionArray['target_id'] = $postID;

					// }
					
					if (!empty($delivery_option)){
						$transactionArray['delivery_option'] = $delivery_option;
					}
					
					$transactionId = $this->UserBraintreeTransaction_model->insertNewTransaction($transactionArray);							
					$transactions[] = $this->UserBraintreeTransaction_model->getTransaction($transactionId[MY_Controller::RESULT_FIELD_NAME]);
								
					if (!empty($productID)) {
						$products = $this->Product_model->getProduct($productID);

						if (count($products) > 0) {
							$product = $products[0];
							
							// This needs to be updated when there is cart-checkout
							$stockLevel = $product['stock_level'];
							if ($stockLevel > 0) {
								if ($stockLevel > 1) {
									// decrease the stock level
									$this->Product_model->updateProduct(
										array(
											'stock_level' => $stockLevel - $quantity, 'updated_at' => time()
										),
										array('id' => $productID)
									);
			
								} else {
									// set the stock level as '0' and set the product as 'Sold out'
									$this->Product_model->updateProduct(
										array(
											'stock_level' => 0, 
											'is_sold' => 1, 
											'updated_at' => time()											
										),
										array('id' => $productID)
									);
			
									// set the relevant posts as 'Sold out'
									$posts = $this->Post_model->getPostInfo(array('product_id' => $productID, 'post_type' => 2));
			
									for ($postIndex = 0; $postIndex < count($posts); $postIndex ++) {
										$this->Post_model->updatePostContent(
											array(
												'is_sold' => 1, 
												'updated_at' => time()
											),
											array('id' => $posts[$postIndex]['id'])
										);
									}
								}

								$this->NotificationHistory_model->insertNewNotification(
									array(
										'user_id' => $toUserID,
										'type' => 4,
										'related_id' => $product['poster_profile_type'],
										'read_status' => 0,
										'send_status' => 0,
										'visible' => 1,
										'text' => " has purchased " . $product['title'],
										'name' => $userInfo[0]['user_name'],
										'profile_image' => $userInfo[0]['pic_url'],
										'updated_at' => time(),
										'created_at' => time()
									)
								);

							} else {
								$retVal[self::RESULT_FIELD_NAME] = false;
								$retVal[self::MESSAGE_FIELD_NAME] = "The product is out of stock.";
							}

						} else {
							$retVal[self::RESULT_FIELD_NAME] = false;
							$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
						}

					} else  if (!empty($variantID)) {
						$productVariants = $this->Product_model->getProductVariation($variantID);	

						if (count($productVariants) > 0) {
							$productVariant = $productVariants[0];	// selected product variant

							$product = $this->Product_model->getProduct($productVariant['product_id'])[0];

							// This needs to be updated when there is cart-checkout
							$stockLevel = $productVariant['stock_level'];
							if ($stockLevel > 0) {
								// decrease the stock level
								$this->Product_model->updateProductVariation(
									array(
										'stock_level' => $stockLevel - $quantity,
										'updated_at' => time()
									),
									array('id' => $variantID)
								);
			
								// get all variations
								$allProductVariants = $this->Product_model->getProductVariations(array('product_id' => $productVariant['product_id']));
								$totalStockLevel = 0;
								for ($variantIndex = 0; $variantIndex < count($allProductVariants); $variantIndex++)  {
									$totalStockLevel += $allProductVariants[$variantIndex]['stock_level'];
								}
			
								if ($totalStockLevel <= 0) {
									// set the product as 'Sold out'
									$this->Product_model->updateProduct(
										array(
											'is_sold' => 1, 'updated_at' => time()
										),
										array('id' => $product['id'])
									);
			
									// set all relevant posts as 'Sold out'
									$posts = $this->Post_model->getPostInfo(array('product_id' => $product['id'], 'post_type' => 2));	
									for ($postIndex = 0; $postIndex < count($posts); $postIndex ++) {
										$this->Post_model->updatePostContent(
											array(
												'is_sold' => 1, 
												'updated_at' => time()
											),
											array('id' => $posts[$postIndex]['id'])
										);
									}
								}

								$this->NotificationHistory_model->insertNewNotification(
									array(
										'user_id' => $toUserID,
										'type' => 4,
										'related_id' => $product['poster_profile_type'],
										'read_status' => 0,
										'send_status' => 0,
										'visible' => 1,
										'text' => " has purchased " . $product['title'],
										'name' => $userInfo[0]['user_name'],
										'profile_image' => $userInfo[0]['pic_url'],
										'updated_at' => time(),
										'created_at' => time()
									)
								);

							} else {
								$retVal[self::RESULT_FIELD_NAME] = false;
								$retVal[self::MESSAGE_FIELD_NAME] = "The product is out of stock.";
							}

						} else {
							$retVal[self::RESULT_FIELD_NAME] = false;
							$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
						}

					} else if (!empty($bookingID)) {
						$bookings = $this->Booking_model->getBooking($bookingID);
						$services= $this->UserService_model->getServiceInfo($bookings[0]['service_id']);

						$this->NotificationHistory_model->insertNewNotification(
							array(
								'user_id' => $toUserID,
								'type' => 12,
								'related_id' => $bookingID,
								'read_status' => 0,
								'send_status' => 0,
								'visible' => 1,
								'text' => " has completed the payment for " . $services[0]['title'],
								'name' => $userInfo[0]['user_name'],
								'profile_image' => $userInfo[0]['pic_url'],
								'updated_at' => time(),
								'created_at' => time()
							)
						);
					}

					$retVal[self::RESULT_FIELD_NAME] = true;
					$retVal[self::MESSAGE_FIELD_NAME] = $transactions;

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "The seller doesn't have payment source.";
				}

			} else {
				$errors = array();
				foreach($SaleTransaction->errors->deepAll() as $error) {
					$errors[] = array("code" => $error->code, "message" => $error->message);
				}

				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = $errors;
			}

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function make_cash_payment() {
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$toUserID = $this->input->post('toUserId');			
			$productID = $this->input->post('product_id');
			$variantID = $this->input->post('variation_id');
			$delivery_option = $this->input->post('delivery_option');		
			$is_business = $this->input->post('is_business');			
			$quantity = $this->input->post('quantity');
			
			$userInfo = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));
			
			$product = array();
			$title = "";
			$related_id = -1;
			
			// to create & send cash transactions
			$transactions = array();

			if (!empty($productID)) {
				$products = $this->Product_model->getProduct($productID);
				if (count($products) > 0) {
					$product = $products[0];

					$title = $product["title"];
					// $related_id = $productID;
					$related_id = $product['poster_profile_type'];

					// This needs to be updated when there is cart-checkout
					$stockLevel = $product['stock_level'];
					if ($stockLevel > 0) {
						if ($stockLevel > 1) {
							// decrease the stock level
							$this->Product_model->updateProduct(
								array(
									'stock_level' => $stockLevel - $quantity, 'updated_at' => time()
								),
								array('id' => $productID)
							);
	
						} else {
							// set the stock level as '0' and set the product as 'Sold out'
							$this->Product_model->updateProduct(
								array(
									'stock_level' => 0, 
									'is_sold' => 1, 
									'updated_at' => time()
								),
								array('id' => $productID)
							);
	
							// set the relevant posts as 'Sold out'
							$posts = $this->Post_model->getPostInfo(array('product_id' => $productID, 'post_type' => 2));
	
							for ($postIndex = 0; $postIndex < count($posts); $postIndex ++) {
								$this->Post_model->updatePostContent(
									array(
										'is_sold' => 1, 
										'updated_at' => time()
									),
									array('id' => $posts[$postIndex]['id'])
								);
							}
						}

					} else {
						$retVal[self::RESULT_FIELD_NAME] = false;
						$retVal[self::MESSAGE_FIELD_NAME] = "The product is out of stock.";
					}

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
				}
			}
				
			if (!empty($variantID)) {
				$productVariants = $this->Product_model->getProductVariation($variantID);				
				if (count($productVariants) > 0) {
					$productVariant = $productVariants[0];	// selected product variant

					$product = $this->Product_model->getProduct($productVariant['product_id'])[0];

					$title = $product['title'];
					// $related_id = $variantID; 
					// $related_id = $product['id'];
					$related_id = $product['poster_profile_type'];

					// This needs to be updated when there is cart-checkout
					$stockLevel = $productVariant['stock_level'];
					if ($stockLevel > 0) {
						// decrease the stock level
						$this->Product_model->updateProductVariation(
							array(
								'stock_level' => $stockLevel - $quantity,
								'updated_at' => time()
							),
							array('id' => $variantID)
						);
	
						// get all variations
						$allProductVariants = $this->Product_model->getProductVariations(array('product_id' => $productVariant['product_id']));
						$totalStockLevel = 0;
						for ($variantIndex = 0; $variantIndex < count($allProductVariants); $variantIndex++)  {
							$totalStockLevel += $allProductVariants[$variantIndex]['stock_level'];
						}
	
						if ($totalStockLevel <= 0) {
							// set the product as 'Sold out'
							$this->Product_model->updateProduct(
								array(
									'is_sold' => 1, 'updated_at' => time()
								),
								array('id' => $product['id'])
							);
	
							// set all relevant posts as 'Sold out'
							$posts = $this->Post_model->getPostInfo(array('product_id' => $product['id'], 'post_type' => 2));	
							for ($postIndex = 0; $postIndex < count($posts); $postIndex ++) {
								$this->Post_model->updatePostContent(
									array(
										'is_sold' => 1, 
										'updated_at' => time()
									),
									array('id' => $posts[$postIndex]['id'])
								);
							}
						}

					} else {
						$retVal[self::RESULT_FIELD_NAME] = false;
						$retVal[self::MESSAGE_FIELD_NAME] = "The product is out of stock.";
					}

				} else {
					$retVal[self::RESULT_FIELD_NAME] = false;
					$retVal[self::MESSAGE_FIELD_NAME] = "Sorry, we were not able to find the product in our record.";
				}
			}

			if ($related_id >= 0) {
				// create & send a notification to the seller
				$this->NotificationHistory_model->insertNewNotification(
					array(
						'user_id' => $toUserID,
						'type' => 4,
						'related_id' => $related_id,
						'read_status' => 0,
						'send_status' => 0,
						'visible' => 1,
						'text' => " has purchased " . $title,
						'name' => $userInfo[0]['user_name'],
						'profile_image' => $userInfo[0]['pic_url'],
						'updated_at' => time(),
						'created_at' => time()
					)
				);

				$manualID = uniqid("ATB_");
				$cash_transaction = array(
					'user_id' => $toUserID,
					'from_to' => $tokenVerifyResult['id'],
					'transaction_id' => $manualID,
					'transaction_type' => "Income",
					'amount' => $product['price'],
					'quantity' => $quantity,
					'payment_method' => 'Cash',
					'payment_source' => $userInfo[0]['user_email'],
					'is_business' => $is_business, 
					'delivery_option' => $delivery_option,
					'created_at' => time()
				);

				if(!empty($variantID)){
					$cash_transaction['purchase_type'] = "product_variant";
					$cash_transaction['target_id'] = $variantID;

				} else if(!empty($productID)){
					$cash_transaction['purchase_type'] = "product";
					$cash_transaction['target_id'] = $productID;
				}

				// creating an income transaction
				$transactionId = $this->UserBraintreeTransaction_model->insertNewTransaction($cash_transaction);
				$transactions[] = $this->UserBraintreeTransaction_model->getTransaction($transactionId[MY_Controller::RESULT_FIELD_NAME]);

				$manualID = uniqid("ATB_");
				$cash_transaction['user_id'] = $tokenVerifyResult['id'];
				$cash_transaction['from_to'] = $toUserID;
				$cash_transaction['transaction_id'] = $manualID;
				$cash_transaction['transaction_type'] = "Sale";
				$cash_transaction['amount'] = -$product['price'];

				// creating a sale transaction
				$transactionId = $this->UserBraintreeTransaction_model->insertNewTransaction($cash_transaction);							
				$transactions[] = $this->UserBraintreeTransaction_model->getTransaction($transactionId[MY_Controller::RESULT_FIELD_NAME]);
	
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "The item you have purchased is reserved for you. Please message the seller to finalise collection/delivery details and complete the transaction.";
			}
			
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_pp_subscription()
	{
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$gateway = new Braintree\Gateway(
				[
					'environment' => $this->config->item('braintree_environment'),
					'merchantId' => $this->config->item('braintree_merchantId'),
					'publicKey' => $this->config->item('braintree_publicKey'),
					'privateKey' => $this->config->item('braintree_privateKey'),
				]
			);

			$paymentNonce = $this->input->post('paymentMethodNonce');
			$customerId = $this->input->post('customerId');

			$paymentMethod = $gateway->paymentMethod()->create(
				[
					'customerId' => $customerId,
					'paymentMethodNonce' => $paymentNonce
				]
			);

			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Payment Error!";

			if ($paymentMethod != null) {
                //$paymentMethodCreateErrors = $paymentMethod->errors;
                //if($paymentMethodCreateErrors == null)
                //{
				$paymentMethodCreateSuccess = $paymentMethod->success;
				if ($paymentMethodCreateSuccess) {
					$paymentMethodResult = $paymentMethod->paymentMethod;
					$paymentMethodToken = $paymentMethodResult->token;

					$SubscriptionCreate = $gateway->subscription()->create(
						[
							'paymentMethodToken' => $paymentMethodToken,
							'planId' => $this->config->item('braintree_planId'),
							'merchantAccountId' => 'myatb'
						]
					);

					if ($SubscriptionCreate->success) {
						$subscriptionInfo = $SubscriptionCreate->subscription;
						$subscriptionId = $subscriptionInfo->id;

						$userId = $tokenVerifyResult['id'];

						$this->load->model('UserBraintree_model');
						$this->load->model('UserBraintreeTransaction_model');
						$this->load->model('NotificationHistory_model');

						$updateResult = $this->UserBraintree_model->updateUserBraintreeCustomerId(
							array('subscription_id' => $subscriptionId, 'subscription_status' => 1, 'updated_at' => time()),
							array('user_id' => $userId)
						);

						$transactionInfo = $subscriptionInfo->transactions;
						$transactionId = $transactionInfo[0]->id;

						$paymentMethod = $this->input->post('paymentMethod');

						$paymentSource = "";
						if ($paymentMethod == "Paypal") {
							$paypalInfo = $transactionInfo[0]->paypal;
							$paymentSource = $paypalInfo['payerEmail'];
						} else {
							$cardInfo = $transactionInfo[0]->creditCard;
							$paymentSource = $cardInfo['last4'];
						}

						$transactionAddResult = $this->UserBraintreeTransaction_model->insertNewTransaction(
							array(
								'user_id' => $userId,
								'transaction_id' => $transactionId,
								'transaction_type' => "Subscription",
								'target_id' => $subscriptionId,
								'amount' => 4.99,
								'payment_method' => $this->input->post('paymentMethod'),
								'payment_source' => $paymentSource,
								'created_at' => time()
							)
						);

						$users = $this->User_model->getOnlyUser(array('id' => $tokenVerifyResult['id']));

						$content = '
			<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;">Your business account has now been created. Please find the terms and conditions below</span></p>
			<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;"><b></b></span></p>';

						$subject = 'ATB Business account created';

						$this->User_model->sendUserEmail($users[0]["user_email"], $subject, $content);

						$this->UserBusiness_model->updateBusinessRecord(
							array('paid' => 1, 'updated_at' => time()),
							array('user_id' => $userId)
						);

						$userInfo = $this->User_model->getOnlyUser(array('id' => $userId));

						$retVal[self::RESULT_FIELD_NAME] = true;
						$retVal[self::MESSAGE_FIELD_NAME] = $subscriptionId;
					}
				}
                //}
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function add_apple_subscription() {
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$userId = $tokenVerifyResult['id'];

			$this->load->model('UserBraintreeTransaction_model');

			$transactionAddResult = $this->UserBraintreeTransaction_model->insertNewTransaction(
				array(
					'user_id' => $userId,
					'transaction_id' => $this->input->post('transaction_id'),
					'transaction_type' => "Subscription",
					'target_id' => $this->input->post('transaction_id'),
					'amount' => 4.99,
					'payment_method' => $this->input->post('payment_method'),
					'payment_source' => "Apple In-app subscription",
					'created_at' => time()
				)
			);

			$users = $this->User_model->getOnlyUser(array('id' => $userId));

			$content = '
				<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;">Your business account has now been created. Please find the terms and conditions below</span></p>
				<p style="font-size: 18px; line-height: 1.2; text-align: center; mso-line-height-alt: 22px; margin: 0;"><span style="color: #808080; font-size: 18px;"><b></b></span></p>';

			$subject = 'ATB Business subscription has been completed';

			// $this->User_model->sendUserEmail($users[0]["user_email"], $subject, $content);
			$this->User_model->sendUserEmail("elitesolution1031@gmail.com", $subject, $content);

			$this->UserBusiness_model->updateBusinessRecord(
				array('paid' => 1, 'updated_at' => time()),
				array('user_id' => $userId)
			);

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "ATB Business subscription has been completed.";

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function get_braintree_client_token()
	{
		$this->load->model('UserBraintree_model');
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		$retVal = array();
		$customerId = "";

		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$userId = $tokenVerifyResult['id'];
			$braintrees = $this->UserBraintree_model->getUserBraintreeInfo(array('user_id' => $userId));

			$gateway = new Braintree\Gateway(
				[
					'environment' => $this->config->item('braintree_environment'),
					'merchantId' => $this->config->item('braintree_merchantId'),
					'publicKey' => $this->config->item('braintree_publicKey'),
					'privateKey' => $this->config->item('braintree_privateKey'),
				]
			);

			if (count($braintrees) > 0) {
				$customers = $gateway->customer()->search(
					[
						Braintree\CustomerSearch::id()->is($braintrees[0]['customer_id'])
					]
				);

				foreach ($customers as $customer) {
					$customerId = $customer->id;
				}
			} else {
				$customers = $gateway->customer()->search(
					[
						Braintree\CustomerSearch::fax()->is($userId)
					]
				);

				foreach ($customers as $customer) {
					$customerId = $customer->id;
				}

				$insResult = $this->UserBraintree_model->insertNewUserBraintreeCustomerId(
					array(
						'user_id' => $userId,
						'customer_id' => $customerId,
						'subscription_id' => '',
						'subscription_status' => 0,
						'receive_address' => '',
						'created_at' => time(),
						'updated_at' => time()
					)
				);
			}

			if ($customerId == "") {
				$userInfo = $this->User_model->getOnlyUser(array('id' => $userId));

				$createCustomerResult = $gateway->customer()->create(
					[
						'firstName' => $userInfo[0]["first_name"],
						'lastName' => $userInfo[0]["last_name"],
						'company' => $userInfo[0]["user_name"],
						'email' => $userInfo[0]["user_email"],
						'phone' => '',
						'fax' => $userId,
						'website' => ''
					]
				);

				if ($createCustomerResult) {
					$customerId = $createCustomerResult->customer->id;

					$updateResult = $this->UserBraintree_model->updateUserBraintreeCustomerId(
						array('customer_id' => $customerId, 'updated_at' => time()),
						array('user_id' => $userId)
					);
				}
			}

			$clientToken = "";

			if ($customerId != "") {
				$clientToken = $gateway->clientToken()->generate(
					[
						'customerId' => $customerId
					]
				);

				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = array('client_token' => $clientToken, 'customer_id' => $customerId);
			} else {
				$retVal[self::RESULT_FIELD_NAME] = false;
				$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
			}
		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}

		echo json_encode($retVal);
	}

	public function can_rate_business() {
		$tokenVerifyResult = $this->verificationToken($this->input->post('token'));
		if ($tokenVerifyResult[self::RESULT_FIELD_NAME]) {
			$userId = $tokenVerifyResult['id'];
			$toUserId = $this->input->post('toUserId');

			// get completed bookings			
			$searchArray = array();
			$searchArray['user_id'] = $userId;
			$searchArray['business_user_id'] = $toUserId;
			$searchArray['state'] = 'complete'; 
			
			$completedBookings = $this->Booking_model->getBookings($searchArray);
			if (count($completedBookings) > 0) {
				$retVal[self::RESULT_FIELD_NAME] = true;
				$retVal[self::MESSAGE_FIELD_NAME] = "Success";
				$retVal[self::EXTRA_FIELD_NAME] = array('can_rate' => '1');

				echo json_encode($retVal);
				exit();
			}

			// get purchased products
			$purchases = $this->UserBraintreeTransaction_model->getPurchasedProductHistory($userId);
			
			for ($index = 0; $index < count($purchases); $index ++) {
				if ($purchases[$index]['purchase_type'] == "product_variant" || $purchases[$index]['purchase_type'] == "product") {
					$products = $purchases[$index]['product'];

					if (count($products) > 0 && $products[0]['user_id'] == $toUserId) {
						$retVal[self::RESULT_FIELD_NAME] = true;
						$retVal[self::MESSAGE_FIELD_NAME] = "Success";
						$retVal[self::EXTRA_FIELD_NAME] = array('can_rate' => '1');

						echo json_encode($retVal);
						exit();
					}
				}
			}

			// get only requsets sent by a week ago
			date_default_timezone_set('UTC');
			$weekAgo = strtotime("-7 days");

			$where = array();
			$where['type'] = '9'; 			// rating requested
			$where['user_id'] = $userId;	// requested to the user who is going to rate the business
			$where['created_at >='] = $weekAgo;

			$notifications = $this->NotificationHistory_model->getNotificationHistory($where);

			if (count($notifications) > 0) {
				// get active bookings
				$searchArray['state'] = 'active'; 	//'complete'
				$activeBookings = $this->Booking_model->getBookings($searchArray);
				
				$activeBookingIds = array();
				foreach ($activeBookings as $activieBooking) {
					array_push($activeBookingIds, $activieBooking['id']);
				}

				for ($ni = 0; $ni < count($notifications); $ni ++) {
					$relatedId = $notifications[$ni]['related_id'];

					if (in_array($related_id, $activeBookingIds)) {
						$retVal[self::RESULT_FIELD_NAME] = true;
						$retVal[self::MESSAGE_FIELD_NAME] = "Success";
						$retVal[self::EXTRA_FIELD_NAME] = array('can_rate' => '1');

						echo json_encode($retVal);
						exit();
					}
				}
			}

			$retVal[self::RESULT_FIELD_NAME] = true;
			$retVal[self::MESSAGE_FIELD_NAME] = "Success";
			$retVal[self::EXTRA_FIELD_NAME] = array('can_rate' => '0');

			echo json_encode($retVal);			

		} else {
			$retVal[self::RESULT_FIELD_NAME] = false;
			$retVal[self::MESSAGE_FIELD_NAME] = "Invalid Credential.";
		}
	}
}
