<?php

function wordpress($app, $username, $parameters) {
    $document_root = DomainLookup_getdocroot($app, $username, array(
        'domain' => $parameters['domain'],
    ));
    if (!$document_root) {
        return array(0, 'Fatal Error #1');
    }
    $mysql = get_mysql($app, $username);
    if (!$mysql) {
        return array(0, 'Fatal Error #2');
    }
    $ssh2_connect = @ssh2_connect(
        $GLOBALS['parameters']['ssh']['hostname'],
        $GLOBALS['parameters']['ssh']['port'],
        array('hostkey' => 'ssh-rsa')
    );
    if (!$ssh2_connect) {
        return array(0, 'Fatal Error #3');
    }
    if (!@ssh2_auth_pubkey_file(
        $ssh2_connect,
        $GLOBALS['parameters']['ssh']['username'],
        $GLOBALS['parameters']['ssh']['keys']['public'],
        $GLOBALS['parameters']['ssh']['keys']['private'],
        ''
    )) {
        return array(0, 'Fatal Error #4');
    }
    $remotes = array(
        'directory' => 'wordpress',
        'file' => 'wordpress.tar.bz2',
        'path' => '/tmp',
    );
    if (!@ssh2_exec($ssh2_connect, sprintf(
        'rm --force "%s/%s" "%s/%s"',
        $remotes['path'],
        $remotes['directory'],
        $remotes['path'],
        $remotes['file']
    ))) {
        return array(0, 'Fatal Error #5');
    }
    if (!@ssh2_scp_send(
        $ssh2_connect,
        __DIR__.'/scripts/wordpress.tar.bz2',
        sprintf('%s/%s', $remotes['path'], $remotes['file']),
        0644
    )) {
        return array(0, 'Fatal Error #6');
    }
    $command = <<<EOD
        cd "%s" \
        && \
        tar -xjpf "%s" 2>&1 \
        && \
        rm --force "%s" 2>&1 \
        && \
        mv --force "%s" "%s/%s" 2>&1 \
        && \
        chown --recursive %s:%s "%s/%s" 2>&1 \
        && \
        cd "%s/%s" 2>&1 \
        && \
        php \
            install.php \
            "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" \
            2>&1
EOD;
    if (!@ssh2_exec($ssh2_connect, sprintf(
        $command,
        $remotes['path'],
        $remotes['file'],
        $remotes['file'],
        $remotes['directory'],
        $document_root,
        $parameters['directory'],
        $username,
        $username,
        $document_root,
        $parameters['directory'],
        $document_root,
        $parameters['directory'],
        $mysql['hostname'],
        $mysql['username'],
        $mysql['password'],
        $mysql['database'],
        $parameters['domain'],
        $parameters['directory'],
        $parameters['site_title'],
        $parameters['username'],
        $parameters['password'],
        $parameters['email'],
        get_uuid(64)
    ))) {
        return array(0, 'Fatal Error #7');
    }

    return array(1, '');
}

function DomainLookup_getdocroot($app, $username, $parameters) {
    $document_root = '';
    $item = $app['stash']->getItem('DomainLookup_getdocroot', $username);
    $document_root = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'getdocroot',
            'module' => 'DomainLookup',
            'user' => $username,
        ), $parameters);
        if ($response->validResponse()) {
            try {
                $document_root = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively()[0]['docroot'];
                $item->set(
                    $document_root, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }

    return $document_root;
}

function AddonDomain_listaddondomains($app, $username) {
    $domains = array();
    $item = $app['stash']->getItem('AddonDomain_listaddondomains', $username);
    $domains = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'listaddondomains',
            'module' => 'AddonDomain',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $domains = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively();
                $item->set(
                    $domains, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($domains, 'usort_AddonDomain_listaddondomains');

    return $domains;
}

function AddonDomain_addaddondomain($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'addaddondomain',
        'module' => 'AddonDomain',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('AddonDomain_listaddondomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function AddonDomain_deladdondomain($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'deladdondomain',
        'module' => 'AddonDomain',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('AddonDomain_listaddondomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function Park_listparkeddomains($app, $username) {
    $domains = array();
    $item = $app['stash']->getItem('Park_listparkeddomains', $username);
    $domains = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'listparkeddomains',
            'module' => 'Park',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $domains = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively();
                $item->set(
                    $domains, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($domains, 'usort_Park_listparkeddomains');

    return $domains;
}

function Park_park($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'park',
        'module' => 'Park',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('Park_listparkeddomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function Park_unpark($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'unpark',
        'module' => 'Park',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('Park_listparkeddomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function SubDomain_listsubdomains($app, $username) {
    $domains = array();
    $item = $app['stash']->getItem('SubDomain_listsubdomains', $username);
    $domains = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'listsubdomains',
            'module' => 'SubDomain',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $domains = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively();
                $item->set(
                    $domains, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($domains, 'usort_SubDomain_listsubdomains');

    return $domains;
}

function SubDomain_addsubdomain($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'addsubdomain',
        'module' => 'SubDomain',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('SubDomain_listsubdomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function SubDomain_delsubdomain($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'delsubdomain',
        'module' => 'SubDomain',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['result'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']
                    ->getItem('SubDomain_listsubdomains', $username)
                    ->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function Cron_get_email($app, $username) {
    $email = '';
    $item = $app['stash']->getItem('Cron_get_email', $username);
    $domains = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'get_email',
            'module' => 'Cron',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $email = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively()[0]['email'];
                $item->set($email, $GLOBALS['parameters']['others']['ttl']);
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }

    return $email;
}

function Cron_set_email($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'set_email',
        'module' => 'Cron',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        try {
            $data = $response->cpanelresult->data->getAllDataRecursively()[0];
            $code = $data['status'];
            $message = $data['reason'];
            if ($code) {
                $app['stash']->getItem('Cron_get_email', $username)->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function Cron_get($app, $username) {
    $lines = array();
    $item = $app['stash']->getItem('Cron_listcron', $username);
    $lines = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'listcron',
            'module' => 'Cron',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $lines = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively();
                if (!empty($lines)) {
                    foreach ($lines as $key => $value) {
                        if (count(array_keys($value)) < 9) {
                            unset($lines[$key]);
                        }
                    }
                }
                $item->set($lines, $GLOBALS['parameters']['others']['ttl']);
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($lines, 'usort_Cron_listcron');

    return $lines;
}

function Cron_set($app, $username, $lines) {
    $lines_ = Cron_get($app, $username);
    if (!empty($lines_)) {
        foreach ($lines_ as $line_) {
            $response = $app['api']->cpanel_api2_request('whostmgr', array(
                'function' => 'remove_line',
                'module' => 'Cron',
                'user' => $username,
            ), array(
                'linekey' => $line_['linekey'],
            ));
        }
    }
    $code = 1;
    $message = '';
    if (!empty($lines)) {
        foreach ($lines as $line) {
            $response = $app['api']->cpanel_api2_request('whostmgr', array(
                'function' => 'add_line',
                'module' => 'Cron',
                'user' => $username,
            ), $line);
            if ($response->validResponse()) {
                try {
                } catch (\Exception $exception) {
                    $code = 0;
                    $message = 'Fatal Error #2';
                }
            } else {
                $code = 0;
                $message = 'Fatal Error #1';
            }
        }
    }
    $app['stash']->getItem('Cron_listcron', $username)->clear();

    return array($code, $message);
}

function Fileman_getdir($app, $username) {
    $directory = '';
    $item = $app['stash']->getItem('Fileman_getdir', $username);
    $directory = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'getdir',
            'module' => 'Fileman',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $directory = urldecode(
                    $response
                        ->cpanelresult
                        ->data
                        ->getAllDataRecursively()[0]['dir']
                );
                $item->set(
                    $directory, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }

    return $directory;
}

function Fileman_savefile($app, $username, $parameters) {
    $response = $app['api']->cpanel_api2_request('whostmgr', array(
        'function' => 'savefile',
        'module' => 'Fileman',
        'user' => $username,
    ), $parameters);
    if ($response->validResponse()) {
        $code = 1;
        $message = '';
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function MysqlFE_listdbs($app, $username) {
    $databases = '';
    $item = $app['stash']->getItem('MysqlFE_listdbs', $username);
    $databases = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->cpanel_api2_request('whostmgr', array(
            'function' => 'listdbs',
            'module' => 'MysqlFE',
            'user' => $username,
        ));
        if ($response->validResponse()) {
            try {
                $databases = $response
                    ->cpanelresult
                    ->data
                    ->getAllDataRecursively();
                $item->set(
                    $databases, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }

    return $databases;
}

function createacct($app, $parameters) {
    $response = $app['api']->whm_api('createacct', $parameters);
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['reason'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function editquota($app, $parameters) {
    $response = $app['api']->whm_api('editquota', $parameters);
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function installssl($app, $parameters) {
    $response = $app['api']->whm_api('installssl', $parameters);
    if ($response->validResponse()) {
        try {
            $code = $response->status;
            $message = $response->statusmsg;
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function limitbw($app, $parameters) {
    $response = $app['api']->whm_api('limitbw', $parameters);
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('showbw', $parameters[0])->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function listaccts($app) {
    $accounts = array();
    $item = $app['stash']->getItem('listaccts');
    $accounts = $item->get(Stash\Item::SP_OLD);

    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->whm_api('listaccts');
        if ($response->validResponse()) {
            try {
                $accounts = $response->acct->getAllDataRecursively();
                $item->set(
                    $accounts, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($accounts, 'usort_listaccts');

    return $accounts;
}

function listips($app) {
    $ip_addresses = array();
    $item = $app['stash']->getItem('listips');
    $ip_addresses = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->whm_api('listips');
        if ($response->validResponse()) {
            try {
                $ip_addresses = $response->result->getAllDataRecursively();
                $item->set(
                    $ip_addresses, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($ip_addresses, 'usort_listips');

    return $ip_addresses;
}

function listpkgs($app) {
    $packages = array();
    $item = $app['stash']->getItem('listpkgs');
    $packages = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->whm_api('listpkgs');
        if ($response->validResponse()) {
            try {
                $packages = $response->package->getAllDataRecursively();
                $item->set(
                    $packages, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }
    usort($packages, 'usort_listpkgs');

    return $packages;
}

function passwd($app, $parameters) {
    $response = $app['api']->whm_api('passwd', $parameters);
    if ($response->validResponse()) {
        try {
            $result = $response->passwd->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function rebuildinstalledssldb($app) {
    $response = $app['api']
        ->factory('whm')
        ->makeQuery('rebuildinstalledssldb', array(
            'api.version' => 1,
        ));
    if ($response->validResponse() && $response->metadata) {
        $code = 1;
        $message = '';
    } else {
        $code = 0;
        $message = $response->error;
    }

    return array($code, $message);
}

function removeacct($app, $username) {
    $response = $app['api']->whm_api('removeacct', array(
        'username' => $username,
    ));
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function setsiteip($app, $parameters) {
    $response = $app['api']->whm_api('setsiteip', $parameters);
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function showbw($app, $username) {
    $bandwidth = 0;
    $item = $app['stash']->getItem('showbw', $username);
    $bandwidth = $item->get(Stash\Item::SP_OLD);
    if ($item->isMiss()) {
        $item->lock();
        $response = $app['api']->whm_api('showbw', array(array(
            'search' => $username,
            'searchtype' => 'user',
        )));
        if ($response->validResponse()) {
            try {
                $bandwidth = $response
                    ->bandwidth
                    ->getAllDataRecursively()[0]['acct'][0]['limit'];
                $item->set(
                    $bandwidth, $GLOBALS['parameters']['others']['ttl']
                );
            } catch (\Exception $exception) {
                $item->clear();
            }
        }
    }

    return $bandwidth;
}

function suspendacct($app, $username) {
    $response = $app['api']->whm_api('suspendacct', array(
        'username' => $username,
    ));
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function unsuspendacct($app, $username) {
    $response = $app['api']->whm_api('unsuspendacct', array(
        'username' => $username,
    ));
    if ($response->validResponse()) {
        try {
            $result = $response->result->getAllDataRecursively()[0];
            $code = $result['status'];
            $message = $result['statusmsg'];
            if ($code) {
                $app['stash']->getItem('listaccts')->clear();
            }
        } catch (\Exception $exception) {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function scp($app, $username, $local, $remote) {
    $code = 1;
    $message = '';
    $ssh2_connect = @ssh2_connect(
        $GLOBALS['parameters']['ssh']['hostname'],
        $GLOBALS['parameters']['ssh']['port'],
        array(
            'hostkey'=>'ssh-rsa',
        )
    );
    if (@ssh2_auth_pubkey_file(
        $ssh2_connect,
        $GLOBALS['parameters']['ssh']['username'],
        $GLOBALS['parameters']['ssh']['keys']['public'],
        $GLOBALS['parameters']['ssh']['keys']['private'],
        ''
    )) {
        if (@ssh2_scp_send($ssh2_connect, $local, $remote, 0644)) {
            if (!@ssh2_exec($ssh2_connect, sprintf(
                'chown %s:%s "%s"',
                $username,
                $username,
                $remote
            ))) {
                $code = 0;
                $message = 'Fatal Error #3';
            }
        } else {
            $code = 0;
            $message = 'Fatal Error #2';
        }
    } else {
        $code = 0;
        $message = 'Fatal Error #1';
    }

    return array($code, $message);
}

function get_account($app, $username) {
    $accounts = listaccts($app);
    if (!empty($accounts)) {
        foreach ($accounts as $account) {
            if ($account['user'] == $username) {
                return $account;
            }
        }
    }

    return array();
}

function get_mysql($app, $username) {
    $hostname = '';
    $response = $app['api']->cpanel_api1_request('whostmgr', array(
        'function' => 'gethost',
        'module' => 'Mysql',
        'user' => $username,
    ));
    if ($response->validResponse()) {
        try {
            $hostname = $response->data->getAllDataRecursively()['result'];
        } catch (\Exception $exception) {
            return false;
        }
    } else {
        return false;
    }
    $mysqli_connect = @mysqli_connect(
        $GLOBALS['parameters']['mysql']['hostname'],
        $GLOBALS['parameters']['mysql']['username'],
        $GLOBALS['parameters']['mysql']['password'],
        '',
        $GLOBALS['parameters']['mysql']['port']
    );
    if (!$mysqli_connect) {
        return false;
    }
    $string_1 = '';
    $string_2 = '';
    $password = get_uuid(8);
    while (true) {
        $uuid = get_uuid(16 - 1 - strlen($username));
        $string_1 = $uuid;
        $string_2 = sprintf('%s_%s', $username, $uuid);
        if (!@mysqli_select_db($mysqli_connect, $string_2)) {
            $response = $app['api']->cpanel_api1_request('whostmgr', array(
                'function' => 'adduser',
                'module' => 'Mysql',
                'user' => $username,
            ), array($string_1, $password));
            if (!$response->validResponse()) {
                return false;
            }
            $response = $app['api']->cpanel_api1_request('whostmgr', array(
                'function' => 'adddb',
                'module' => 'Mysql',
                'user' => $username,
            ), array($string_1));
            if (!$response->validResponse()) {
                return false;
            }
            $response = $app['api']->cpanel_api1_request('whostmgr', array(
                'function' => 'adduserdb',
                'module' => 'Mysql',
                'user' => $username,
            ), array($string_2, $string_2, 'all'));
            if (!$response->validResponse()) {
                return false;
            }
            break;
        }
    }
    @mysqli_close($mysqli_connect);

    return array(
        'hostname' => $hostname,
        'username' => $string_2,
        'password' => $password,
        'database' => $string_2,
    );
}

function get_uuid($length)
{
    $characters = array(
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z',
        'a',
        'b',
        'c',
        'd',
        'e',
        'f',
        'g',
        'h',
        'i',
        'j',
        'k',
        'l',
        'm',
        'n',
        'o',
        'p',
        'q',
        'r',
        's',
        't',
        'u',
        'v',
        'w',
        'x',
        'y',
        'z',
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
    );
    $string = array();
    for ($index = 0; $index < $length; $index++) {
        $string[] = $characters[array_rand($characters)];
    }

    return implode('', $string);
}

function set_mysql($app, $username, $parameters) {
    $hostname = '';
    $response = $app['api']->cpanel_api1_request('whostmgr', array(
        'function' => 'gethost',
        'module' => 'Mysql',
        'user' => $username,
    ));
    if ($response->validResponse()) {
        try {
            $hostname = $response->data->getAllDataRecursively()['result'];
        } catch (\Exception $exception) {
            return array(0, 'Invalid Hostname');
        }
    } else {
        return array(0, 'Invalid Hostname');
    }
    $response = $app['api']->cpanel_api1_request('whostmgr', array(
        'function' => 'adduser',
        'module' => 'Mysql',
        'user' => $username,
    ), array($parameters['database'], $parameters['password']));
    if (!$response->validResponse()) {
        return array(0, 'Fatal Error #1');
    }
    $response = $app['api']->cpanel_api1_request('whostmgr', array(
        'function' => 'adddb',
        'module' => 'Mysql',
        'user' => $username,
    ), array($parameters['database']));
    if (!$response->validResponse()) {
        return array(0, 'Fatal Error #2');
    }
    $database = sprintf('%s_%s', $username, $parameters['database']);
    $response = $app['api']->cpanel_api1_request('whostmgr', array(
        'function' => 'adduserdb',
        'module' => 'Mysql',
        'user' => $username,
    ), array($database, $database, 'all'));
    if (!$response->validResponse()) {
        return array(0, 'Fatal Error #3');
    }

    $app['stash']->getItem('MysqlFE_listdbs', $username)->clear();

    return array(1, '');
}

function usort_AddonDomain_listaddondomains($a, $b) {
    if ($a['domain'] == $b['domain']) {
        return 0;
    }

    return ($a['domain'] < $b['domain']) ? -1 : 1;
}

function usort_Park_listparkeddomains($a, $b) {
    if ($a['domain'] == $b['domain']) {
        return 0;
    }

    return ($a['domain'] < $b['domain']) ? -1 : 1;
}

function usort_SubDomain_listsubdomains($a, $b) {
    if ($a['domain'] == $b['domain']) {
        return 0;
    }

    return ($a['domain'] < $b['domain']) ? -1 : 1;
}

function usort_Cron_listcron($a, $b) {
    if ($a['count'] == $b['count']) {
        return 0;
    }

    return ($a['count'] < $b['count']) ? -1 : 1;
}

function usort_listaccts($a, $b) {
    if ($a['user'] == $b['user']) {
        return 0;
    }

    return ($a['user'] < $b['user']) ? -1 : 1;
}

function usort_listips($a, $b) {
    if ($a['ip'] == $b['ip']) {
        return 0;
    }

    return ($a['ip'] < $b['ip']) ? -1 : 1;
}

function usort_listpkgs($a, $b) {
    if ($a['name'] == $b['name']) {
        return 0;
    }

    return ($a['name'] < $b['name']) ? -1 : 1;
}
