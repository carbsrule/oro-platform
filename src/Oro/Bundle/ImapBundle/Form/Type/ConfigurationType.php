<?php

namespace Oro\Bundle\ImapBundle\Form\Type;

use Oro\Bundle\EmailBundle\Form\Type\EmailFolderTreeType;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\ImapBundle\Form\EventListener\ApplySyncSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\DecodeFolderSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\OriginFolderSubscriber;
use Oro\Bundle\ImapBundle\Form\Model\AccountTypeModel;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Used in System Configuration to set IMAP parameters in Email Configuration
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ConfigurationType extends AbstractType
{
    const NAME = 'oro_imap_configuration';

    /** @var SymmetricCrypterInterface */
    protected $encryptor;

    /** @var TokenAccessorInterface */
    protected $tokenAccessor;

    /** @var TranslatorInterface */
    protected $translator;

    public function __construct(
        SymmetricCrypterInterface $encryptor,
        TokenAccessorInterface $tokenAccessor,
        TranslatorInterface $translator
    ) {
        $this->encryptor = $encryptor;
        $this->tokenAccessor = $tokenAccessor;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new DecodeFolderSubscriber());
        $this->modifySettingsFields($builder);
        $this->addPrepopulatePasswordEventListener($builder);
        $this->addNewOriginCreateEventListener($builder);
        $this->addOwnerOrganizationEventListener($builder);
        $builder->addEventSubscriber(new ApplySyncSubscriber());
        $builder->addEventSubscriber(new OriginFolderSubscriber());
        $this->addEnableSMTPImapListener($builder);
        $this->finalDataCleaner($builder);

        $builder
            ->add('useImap', CheckboxType::class, [
                'label'    => 'oro.imap.configuration.use_imap.label',
                'attr'     => ['class' => 'imap-config check-connection'],
                'required' => false,
                'mapped'   => false,
                'tooltip'  => 'oro.imap.configuration.use_imap.tooltip'
            ])
            ->add('imapHost', TextType::class, [
                'label'    => 'oro.imap.configuration.imap_host.label',
                'required' => false,
                'attr'     => ['class' => 'critical-field imap-config check-connection switchable-field'],
                'tooltip'  => 'oro.imap.configuration.tooltip',
            ])
            ->add('imapPort', NumberType::class, [
                'label'    => 'oro.imap.configuration.imap_port.label',
                'attr'     => ['class' => 'imap-config check-connection switchable-field'],
                'required' => false
            ])
            ->add('imapEncryption', ChoiceType::class, [
                'label'       => 'oro.imap.configuration.imap_encryption.label',
                'choices'     => ['SSL' => 'ssl', 'TLS' => 'tls'],
                'attr'        => ['class' => 'imap-config check-connection switchable-field'],
                'empty_data'  => null,
                'placeholder' => '',
                'required'    => false
            ])
            ->add('accountType', HiddenType::class, [
                'required'  => true,
                'data'      => AccountTypeModel::ACCOUNT_TYPE_OTHER,
                'attr' => [
                    'class' => 'check-connection',
                ]
            ])
            ->add('useSmtp', CheckboxType::class, [
                'label'    => 'oro.imap.configuration.use_smtp.label',
                'attr'     => ['class' => 'smtp-config check-connection'],
                'required' => false,
                'mapped'   => false,
                'tooltip'  => 'oro.imap.configuration.use_smtp.tooltip'
            ])
            ->add('smtpHost', TextType::class, [
                'label'    => 'oro.imap.configuration.smtp_host.label',
                'attr'     => ['class' => 'critical-field smtp-config check-connection switchable-field'],
                'required' => false,
                'tooltip'  => 'oro.imap.configuration.tooltip',
            ])
            ->add('smtpPort', NumberType::class, [
                'label'    => 'oro.imap.configuration.smtp_port.label',
                'attr'     => ['class' => 'smtp-config check-connection switchable-field'],
                'required' => false
            ])
            ->add('smtpEncryption', ChoiceType::class, [
                'label'       => 'oro.imap.configuration.smtp_encryption.label',
                'choices'     => ['SSL' => 'ssl', 'TLS' => 'tls'],
                'attr'        => ['class' => 'smtp-config check-connection switchable-field'],
                'empty_data'  => null,
                'placeholder' => '',
                'required'    => false
            ])
            ->add('user', TextType::class, [
                'label'    => 'oro.imap.configuration.user.label',
                'required' => true,
                'attr'     => ['class' => 'critical-field check-connection'],
                'tooltip'  => 'oro.imap.configuration.tooltip',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'oro.imap.configuration.password.label',
                'required' => true,
                'attr' => [
                    'class' => 'check-connection',
                    'autocomplete' => 'off'
                ]
            ]);
        if ($options['add_check_button']) {
            $builder->add('check_connection', CheckButtonType::class, [
                'label' => $this->translator->trans('oro.imap.configuration.connect_and_retrieve_folders')
            ]);
        }
        $builder->add('folders', EmailFolderTreeType::class, [
                'label'   => $this->translator->trans('oro.email.folders.label'),
                'attr'    => ['class' => 'folder-tree'],
                'tooltip' => 'If a folder is uncheked, all the data saved in it will be deleted',
            ]);
    }

    protected function addPrepopulatePasswordEventListener(FormBuilderInterface $builder)
    {
        $encryptor = $this->encryptor;
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($encryptor) {
                $data = (array) $event->getData();
                /** @var UserEmailOrigin|null $entity */
                $entity = $event->getForm()->getData();
                $filtered = array_filter(
                    $data,
                    function ($item) {
                        return !empty($item);
                    }
                );
                if (count($filtered) > 0) {
                    $oldPassword = $event->getForm()->get('password')->getData();
                    if (empty($data['password']) && $oldPassword) {
                        // populate old password
                        $data['password'] = $oldPassword;
                    } else {
                        $data['password'] = $encryptor->encryptData($data['password']);
                    }
                    $event->setData($data);
                } elseif ($entity instanceof UserEmailOrigin) {
                    $event->getForm()->setData(null);
                }
            },
            4
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function addNewOriginCreateEventListener(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = (array) $event->getData();
                $data['accountType'] = AccountTypeModel::ACCOUNT_TYPE_OTHER;
                /** @var UserEmailOrigin|null $entity */
                $entity = $event->getForm()->getData();
                $filtered = array_filter(
                    $data,
                    function ($item) {
                        return !empty($item);
                    }
                );
                if (count($filtered) > 0) {
                    if ($entity instanceof UserEmailOrigin
                        && $entity->getImapHost() !== null
                        && array_key_exists('imapHost', $data) && $data['imapHost'] !== null
                        && array_key_exists('user', $data) && $data['user'] !== null
                        && ($entity->getImapHost() !== $data['imapHost']
                            || $entity->getUser() !== $data['user'])
                    ) {
                        /*
                         * In case when critical fields were changed, configuration should be reset.
                         *  - When imap or smtp was disabled, don't create new configuration
                         *  - If imap or smtp are still enabled create a new one.
                         */
                        if ((!array_key_exists('useImap', $data) || $data['useImap'] === 0)
                            && (!array_key_exists('useSmtp', $data) || $data['useSmtp'] === 0)
                        ) {
                            $newConfiguration = null;
                            $event->setData(null);
                        } else {
                            $newConfiguration = new UserEmailOrigin();
                        }
                        $event->getForm()->setData($newConfiguration);
                    }
                } elseif ($entity instanceof UserEmailOrigin) {
                    $event->getForm()->setData(null);
                }
            },
            3
        );
    }

    protected function addOwnerOrganizationEventListener(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var UserEmailOrigin $data */
                $data = $event->getData();
                if ($data !== null) {
                    if (($data->getOwner() === null) && ($data->getMailbox() === null)) {
                        $data->setOwner($this->tokenAccessor->getUser());
                    }
                    if ($data->getOrganization() === null) {
                        $organization = $this->tokenAccessor->getOrganization()
                            ? $this->tokenAccessor->getOrganization()
                            : $this->tokenAccessor->getUser()->getOrganization();
                        $data->setOrganization($organization);
                    }

                    $event->setData($data);
                }
            }
        );
    }

    protected function modifySettingsFields(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = (array)$event->getData();
                $entity = $event->getForm()->getData();

                if ($entity instanceof UserEmailOrigin) {
                    /*
                     * If useImap is disabled unset imap related data and set imap host to empty string.
                     * Empty string as host will cause origin to be recreated if necessary.
                     * Old origin will be disabled and later removed in cron job.
                     */
                    if (!array_key_exists('useImap', $data) || $data['useImap'] === 0) {
                        unset($data['imapHost'], $data['imapPort'], $data['imapEncryption']);
                        $data['imapHost'] = '';
                    }
                    /*
                     * If smtp is disabled, unset smtp related data.
                     */
                    if (!array_key_exists('useSmtp', $data) || $data['useSmtp'] === 0) {
                        unset($data['smtpHost'], $data['smtpPort'], $data['smtpEncryption']);
                    }
                    $event->setData($data);
                }
            },
            6
        );
    }

    protected function finalDataCleaner(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = (array)$event->getData();
                $filtered = array_filter(
                    $data,
                    function ($item) {
                        return !empty($item);
                    }
                );

                if (!count($filtered)) {
                    $event->getForm()->remove('useImap');
                    $event->getForm()->remove('useSmtp');
                    $event->getForm()->setData(null);
                }
            },
            1
        );
    }

    public function addEnableSMTPImapListener(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $formEvent) {
                /** @var UserEmailOrigin $data */
                $data = $formEvent->getData();
                if ($data !== null) {
                    $form = $formEvent->getForm();
                    if ($data->getImapHost() !== null) {
                        $form->get('useImap')->setData(true);
                    }
                    if ($data->getSmtpHost() !== null) {
                        $form->get('useSmtp')->setData(true);
                    }
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => UserEmailOrigin::class,
            'required'          => false,
            'constraints'       => new Valid(),
            'add_check_button'  => true,
            'skip_folders_validation' => false,
            'validation_groups' => function (FormInterface $form) {
                $groups = [];

                $isSubmitted = $form->isSubmitted() === true;
                if (($form->has('useImap') && $form->get('useImap')->getData() === true) || !$isSubmitted) {
                    array_push($groups, 'Imap', 'ImapConnection');

                    if (!$form->getConfig()->getOption('skip_folders_validation')) {
                        $groups[] = 'CheckFolderSelection';
                    }
                }
                if (($form->has('useSmtp') && $form->get('useSmtp')->getData() === true) || !$isSubmitted) {
                    array_push($groups, 'Smtp', 'SmtpConnection');
                }

                return $groups;
            },
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return self::NAME;
    }
}
