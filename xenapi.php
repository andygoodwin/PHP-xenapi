<?
/*
 *    PHP XenAPI v1.0
 *    a class for XenServer API calls
 *
 *    Copyright (C) 2010 Andy Goodwin <andyg@unf.net>
 *
 *    This class requires xml-rpc, PHP5, and curl.
 *
 *    This program is free software; you can redistribute it and/or
 *    modify it under the terms of the GNU General Public License
 *    as published by the Free Software Foundation; either version 2
 *    of the License, or (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program; if not, write to the Free Software
 *    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 */

class XenApi {
    private $_url;

    private $_session_id;
    private $_user;
    private $_password;

    function __construct ($url, $user, $password) {
        $r = $this->xenrpc_request($url, $this->xenrpc_method('session.login_with_password', array($user, $password)));
        if (is_array($r) && $r['Status'] == 'Success') {
            $this->_session_id = $r['Value'];
            $this->_url = $url;
            $this->_user = $user;
            $this->_password = $password;
        } else {
            echo "API failure.  (" . implode(' ', $r['ErrorDescription']) . ")\n";  exit;
        }
    }

    function __call($name, $args) {
        if (!is_array($args)) {
            $args = array();
        }
        list($mod, $method) = explode('_', $name, 2);
        $ret = $this->xenrpc_parseresponse($this->xenrpc_request($this->_url, 
                  $this->xenrpc_method($mod . '.' . $method, array_merge(array($this->_session_id), $args))));
        return $ret;
    }

    function xenrpc_parseresponse($response) {
        if (!@is_array($response) && !@$response['Status']) {
            echo "API failure.  (500)\n";  exit;
        } else {
            if ($response['Status'] == 'Success') {
               $ret = $response['Value'];
            } else {
               if ($response['ErrorDescription'][0] == 'SESSION_INVALID') {
                   $r = $this->xenrpc_request($url, $this->xenrpc_method('session.login_with_password', 
                               array($this->_user, $this->_password, '1.3')));
                   if (!is_array($r) && $r['Status'] == 'Success') {
                       $this->_session_id = $r['Value'];
                   } else {
                       echo "API failure.  (session)\n";  exit;
                   }
               } else {
                   echo "API failure.  (" . implode(' ', $response['ErrorDescription']) . ")\n";  exit;
               }
            }
        }
        return $ret;
    }

    function xenrpc_method($name, $params) {
        $ret = xmlrpc_encode_request($name, $params);

        return $ret;
    }

    function xenrpc_request($url, $req) {
        $headers = array('Content-type: text/xml', 'Content-length: ' . strlen($req));

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $req); 

        $resp = curl_exec($ch);
        curl_close($ch); 

        $ret = xmlrpc_decode($resp);

        return $ret;
        }
}

