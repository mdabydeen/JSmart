<?php

    /**
     * @author Joshua Kissoon
     * @date 20121227
     * @description Class that contains user functionality for core JSmart users
     */
    class JSmartUser implements User
    {

        public $uid, $username, $status;
        private $password;
        private $user_table = "user";
        private static $usertbl = "user";
        private $roles = array(), $permissions = array();

        /* Define error handlers */
        public static $ERROR_INCOMPLETE_DATA = 00001;

        /**
         * @desc Constructor method for the user class, loads the user
         * @param $uid The id of the user to load
         * @return Whether the load was successful or not
         */
        public function __construct($uid = 0)
        {
            if (isset($uid) && valid($uid))
            {
                $this->uid = $uid;
                return $this->load();
            }
            else
            {
                $this->uid = 0;
                $this->username = "Anonymous";
                $this->roles[1] = "anonymous";
            }
        }

        /**
         * @desc Method that loads the user data from the database
         * @param $uid The id of the user to load
         * @return Whether the load was successful or not
         */
        public function load($uid = null)
        {
            if (!valid($this->uid) && !valid($uid))
            {
                /* If we have no uid to load the user by */
                return false;
            }
            $this->uid = valid($this->uid) ? $this->uid : $uid;
            if ($this->loadUserInfo())
            {
                $this->loadRoles();
                $this->loadPermissions();
                return true;
            }
            else
            {
                return false;
            }
        }

        /**
         * @desc Method that loads the basic user information from the database
         * @return Whether the load was successful or not
         */
        public function loadUserInfo()
        {
            if (!valid($this->uid))
            {
                return false;
            }
            global $DB;
            $args = array(":uid" => $this->uid);
            $sql = "SELECT * FROM $this->user_table u WHERE uid=':uid' LIMIT 1";
            $rs = $DB->query($sql, $args);
            $cuser = $DB->fetchObject($rs);
            if (isset($cuser->uid) && valid($cuser->uid))
            {
                foreach ($cuser as $key => $value)
                {
                    $this->$key = $value;
                }
            }
            else
            {
                return false;
            }
        }

        /**
         * @desc Loads the roles that a user have
         */
        private function loadRoles()
        {
            global $DB;
            $roles = $DB->query("SELECT ur.rid, r.role FROM user_role ur LEFT JOIN role r ON (r.rid = ur.rid) WHERE uid='$this->uid'");
            while ($role = $DB->fetchObject($roles))
            {
                $this->roles[$role->rid] = $role->role;
            }
        }

        /**
         * @return The roles this user have
         */
        public function getRoles()
        {
            return $this->roles;
        }

        /**
         * @desc Load the permissions for this user from the database
         */
        private function loadPermissions()
        {
            if (count($this->roles) < 1)
            {
                return false;
            }

            global $DB;

            $rids = implode(", ", array_keys($this->roles));
            $rs = $DB->query("SELECT permission FROM role_permission WHERE rid IN ($rids)");
            while ($perm = $DB->fetchObject($rs))
            {
                $this->permissions[$perm->permission] = $perm->permission;
            }
        }

        /**
         * @desc Hash the password and set the user's object password. The password is not permanently saved to the database
         */
        public function setPassword($password)
        {
            if (!isset($this->username) || !valid($this->username))
            {
                return false;
            }
            $this->password = $this->hashPassword($password);
        }

        /**
         * @desc Here we check if this password given here is that of the user
         */
        public function isUserPassword($password)
        {
            return ($this->password == $this->hashPassword($password)) ? true : false;
        }

        /**
         * @desc Saves the user's password to the database
         * @return Boolean whether the save was successful
         */
        private function savePassword()
        {
            global $DB;
            return $DB->updateFields($this->user_table, array("password" => $this->password), $where = "uid='$this->uid'");
        }

        /**
         * @desc Hashes the user's password using a salt
         * @return The hashed password
         * @todo Move the salt to the main settings.php file so that the website owner can update their hash
         */
        private function hashPassword($password)
        {
            $salt = md5($this->username . "K<47`5n9~8H5`*^Ks.>ie5&");
            return sha1($salt . $password);
        }

        /**
         * @desc Adds a new user to the database
         */
        public function addUser()
        {
            if (!$this->isUsernameAvail() || $this->isEmailInUse())
            {
                return false;
            }
            if (!valid($this->username) || !valid($this->email) || !valid($this->password))
            {
                return JSmartUser::$ERROR_INCOMPLETE_DATA;
            }
            global $DB;
            $args = array(
                ":username" => $this->username,
                ":email" => $this->email,
                ":first_name" => isset($this->first_name) ? $this->first_name : "",
                ":last_name" => isset($this->last_name) ? $this->last_name : "",
                ":other_name" => isset($this->other_name) ? $this->other_name : "",
                ":dob" => isset($this->dob) ? $this->dob : "",
            );

            $sql = "INSERT INTO $this->user_table (username, password, email, first_name, last_name, other_name, dob)
                VALUES(':username', '$this->password', ':email', ':first_name', ':last_name', ':other_name', ':dob')";
            if ($DB->query($sql, $args))
            {
                $this->uid = $DB->lastInsertId();
                $this->saveRoles();
                return true;
            }
            else
            {
                return false;
            }
        }

        /**
         * @desc Adds a new role to a user
         */
        public function addRole($rid)
        {
            global $DB;
            $res = $DB->query("SELECT role FROM role WHERE rid='::rid'", array('::rid' => $rid));
            $role = $DB->fetchObject($res);
            if (isset($role->role) && valid($role->role))
            {
                $this->roles[$rid] = $role->role;
                return true;
            }
            return false;
        }

        /**
         * @desc Saves this user's roles to the Database
         */
        public function saveRoles()
        {
            if (!self::isUser($this->uid))
            {
                return false;
            }

            global $DB;

            /* Remove all the roles this user had */
            $DB->query("DELETE FROM user_role WHERE uid='$this->uid'");

            foreach ((array) $this->roles as $rid => $role)
            {
                $DB->query("INSERT INTO user_role (uid, rid) VALUES ('::uid', '::rid')", array('::rid' => $rid, '::uid' => $this->uid));
            }

            return true;
        }

        /**
         * @desc Check if the username and password is valid
         * @return Boolean whether the data is valid or not
         */
        public function authenticate()
        {
            global $DB;
            $args = array(":username" => $this->username);
            $sql = "SELECT uid FROM $this->user_table WHERE username=':username' and password='$this->password' LIMIT 1";
            $cuser = $DB->fetchObject($DB->query($sql, $args));
            if (isset($cuser->uid) && valid($cuser->uid))
            {
                /* Login Successful, check user status */
                $this->uid = $cuser->uid;
                $this->load();
                return true;
            }
            else
            {
                /* Authentication failed */
                return false;
            }
        }

        /**
         * @desc Checks if a username is available
         * @param $username The username to check whether it is available 
         */
        public function isUsernameAvail($username = null)
        {
            if (!valid($username) && !valid($this->username))
            {
                return false;
            }
            $this->username = valid($username) ? $username : $this->username;

            global $DB;
            $DB->query("SELECT username FROM $this->user_table WHERE username='::un'", array("::un" => $this->username));
            $temp = $DB->fetchObject();
            return (isset($temp->username) && valid($temp->username)) ? false : true;
        }

        /**
         * @desc Checks if an email address is in use 
         */
        public function isEmailInUse($email = null)
        {
            if (!valid($email) && !valid($this->email))
            {
                return false;
            }
            $this->email = valid($email) ? $email : $this->email;

            global $DB;
            $DB->query("SELECT email FROM $this->user_table WHERE email='::email'", array("::email" => $this->email));
            $temp = $DB->fetchObject();
            return (isset($temp->email) && valid($this->email)) ? $temp->email : false;
        }

        /**
         * @desc Checks if this is a user of the system
         * @param $uid The user of the user to check for
         * @return Boolean Whether this is a system user or not
         */
        public static function isUser($uid)
        {
            if (!valid($uid))
            {
                return false;
            }

            global $DB;
            $args = array("::uid" => $uid);
            $sql = "SELECT uid FROM " . self::$usertbl . " WHERE uid='::uid'";
            $res = $DB->query($sql, $args);
            $user = $DB->fetchObject($res);
            return (isset($user->uid) && valid($user->uid)) ? true : false;
        }

        /**
         * @desc Deletes a user from the system
         * @param $uid The user ID of the user to delete
         * @return Boolean Whether the user was deleted or not
         */
        public static function delete($uid)
        {
            if (!self::isUser($uid))
            {
                return false;
            }

            global $DB;
            return $DB->query("DELETE FROM " . self::$usertbl . " WHERE uid='::uid'", array("::uid" => $uid));
        }

        /**
         * @desc Set the user's email
         * @return Boolean Whether the email was successfully set
         */
        public function setEmail($email)
        {
            if (valid($email))
            {
                $this->email = $email;
                return true;
            }
            else
            {
                return false;
            }
        }

        /**
         * @desc Check if the user has the specified permission
         * @param $permission The permission to check if the user have
         * @return Boolean Whether the user has the permission
         */
        public function hasPermission($permission)
        {
            if (!valid($permission))
            {
                return false;
            }
            return (key_exists($permission, $this->permissions)) ? true : false;
        }

        /**
         * @desc Grabs the user's status from the database
         * @return The user's current status
         */
        public function getStatus()
        {
            if (!valid($this->status))
            {
                /* If the status is not set in the user object, load it */
                global $DB;
                $this->status = $DB->getFieldValue($this->user_table, "status", "uid = $this->uid");
            }
            return $this->status;
        }

        /**
         * @desc Update this user's status
         * @param $sid The status id of the user's new status
         * @return Whether the user's status is valid or not
         */
        public function setStatus($sid)
        {
            if (!valid($sid))
            {
                return false;
            }

            global $DB;

            /* Check if its a valid user's status */
            $args = array("::status" => $sid);
            $res = $DB->fetchObject($DB->query("SELECT sid FROM user_status WHERE sid='::status'", $args));
            if (!isset($res->sid) || !valid($res->sid))
            {
                return false;
            }

            /* Its a valid user status, update this user's status */
            $args['::uid'] = $this->uid;
            return $DB->query("UPDATE user SET status='::status' WHERE uid = '::uid'", $args);
        }

        /* METHOD IMPLEMENTATIONS FROM THE USER INTERFACE */

        /**
         * @desc Method that returns the user's ID number, most likely as used in the database
         */
        public function getUserID()
        {
            return $this->uid;
        }

        /**
         * @desc Method that returns the username used to identify this user
         */
        public function getUsername()
        {
            return $this->username;
        }

    }
    