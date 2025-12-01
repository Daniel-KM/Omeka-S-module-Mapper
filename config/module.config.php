<?php declare(strict_types=1);

namespace Mapper;

return [
    'service_manager' => [
        'factories' => [
            Stdlib\Mapper::class => Service\Stdlib\MapperFactory::class,
            Stdlib\MapperConfig::class => Service\Stdlib\MapperConfigFactory::class,
        ],
        'aliases' => [
            'Mapper\Mapper' => Stdlib\Mapper::class,
            'Mapper\MapperConfig' => Stdlib\MapperConfig::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'mappers' => Api\Adapter\MapperAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\MappingForm::class => Form\MappingForm::class,
            Form\MappingDeleteForm::class => Form\MappingDeleteForm::class,
        ],
        'factories' => [
            Form\Element\MapperSelect::class => Service\Form\Element\MapperSelectFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'Mapper\Controller\Admin\Index' => Service\Controller\Admin\IndexControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'mapper' => Service\ControllerPlugin\MapperFactory::class,
            'mapperConfigList' => Service\ControllerPlugin\MapperConfigListFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'mapper' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/mapper',
                            'defaults' => [
                                '__NAMESPACE__' => 'Mapper\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'Index',
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '[/:action]',
                                    'constraints' => [
                                        'action' => 'index|browse|add',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                                // Higher priority than 'id' route to match actions first,
                                // since add, browse and index can be id below.
                                'priority' => 1,
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => 'show|edit|copy|delete',
                                        // Action names above are automatically managed
                                        // with priority above.
                                        'id' => '[a-zA-Z0-9_-]+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'mapper' => [
                'label' => 'Mapper', // @translate
                'route' => 'admin/mapper',
                'resource' => 'Mapper\Controller\Admin\Index',
                'class' => 'o-icon- fa-exchange-alt',
                'pages' => [
                    [
                        'route' => 'admin/mapper/default',
                        'controller' => 'index',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/mapper/id',
                        'controller' => 'index',
                        'action' => 'show',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/mapper/id',
                        'controller' => 'index',
                        'action' => 'add',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/mapper/id',
                        'controller' => 'index',
                        'action' => 'delete',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/mapper/id',
                        'controller' => 'index',
                        'action' => 'delete-confirm',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => \Laminas\I18n\Translator\Loader\Gettext::class,
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'mapper' => [
    ],
];
