<?php

    /**
     * @author Joshua Kissoon
     * @date 20130323
     * @descriptin A class that is used to manipulate any URL
     */
    class URL
    {

        private $base_url;
        public $args = array();

        /**
         * @desc Get the URL sections 
         * @param $url The URL to process
         */
        public function __construct($url)
        {
            $url = explode("?", $url);
            $this->base_url = $url[0];

            /* Get the URL args */
            if (isset($url[1]))
            {
                $args = rtrim(ltrim($url[1], "&"), "&"); // Remove extra &'s from the start and end of the URL 

                $parts = explode("&", $args);
                foreach ((array) $parts as $part)
                {
                    $part = explode("=", $part);
                    $this->args[$part[0]] = $part[1];
                }
            }
        }

        /**
         * @desc Add a new argument to the URL
         * @param $key The title of the argument to add
         * @param $value The value of the argument to add
         */
        public function addArg($key, $value)
        {
            $this->args[$key] = $value;
        }

        /**
         * @desc Remove an argument from the URL
         * @param $key The key of the argument to remove
         */
        public function removeArg($key)
        {
            if (isset($this->args[$key]))
            {
                unset($this->args[$key]);
            }
        }

        /**
         * @desc Method that builds and returns the URL
         */
        public function getURL()
        {
            return $this->base_url . "?" . http_build_query($this->args);
        }

    }
    