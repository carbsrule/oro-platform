<?php

namespace Oro\Bundle\AddressBundle\Form\EventListener;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Repository\RegionRepository;
use Oro\Bundle\AddressBundle\Form\Type\RegionType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;

class AddressCountryAndRegionSubscriber implements EventSubscriberInterface
{
    private $om;

    /**
     * Form factory.
     *
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * Constructor.
     */
    public function __construct(ObjectManager $om, FormFactoryInterface $factory)
    {
        $this->om = $om;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT   => 'preSubmit'
        );
    }

    /**
     * Removes or adds a region field based on the country set.
     */
    public function preSetData(FormEvent $event)
    {
        $address = $event->getData();
        $form = $event->getForm();

        if (null === $address) {
            return;
        }

        /** @var $country Country */
        $country = $address->getCountry();

        if (null === $country) {
            return;
        }

        if ($country->hasRegions()) {
            if ($form->has('region')) {
                $regionTypeConfig = $form->get('region')->getConfig();
                $config = $regionTypeConfig->getOptions();
                unset($config['choices']);
                $formType = get_class($regionTypeConfig->getType()->getInnerType());
            } else {
                $config = array();
                $formType = RegionType::class;
            }

            $config['country'] = $country;
            $config['query_builder'] = $this->getRegionClosure($country);

            if (array_key_exists('auto_initialize', $config)) {
                $config['auto_initialize'] = false;
            }

            $form->add(
                $this->factory->createNamed(
                    'region',
                    $formType,
                    $address->getRegion(),
                    $config
                )
            );
        }
    }

    /**
     * Removes or adds a region field based on the country set on submitted form.
     */
    public function preSubmit(FormEvent $event)
    {
        $data = $event->getData();

        /** @var $country Country */
        $country = $this->om->getRepository('OroAddressBundle:Country')
            ->find(isset($data['country']) ? $data['country'] : false);

        if ($country && $country->hasRegions()) {
            $form = $event->getForm();

            $config = $form->get('region')->getConfig()->getOptions();
            unset($config['choices']);

            $config['country'] = $country;
            $config['query_builder'] = $this->getRegionClosure($country);

            if (array_key_exists('auto_initialize', $config)) {
                $config['auto_initialize'] = false;
            }

            $form->add(
                $this->factory->createNamed(
                    'region',
                    get_class($form->get('region')->getConfig()->getType()->getInnerType()),
                    null,
                    $config
                )
            );

            if (!$form->getData()
                || ($form->getData() && !$form->getData()->getRegionText())
                || !empty($data['region'])
            ) {
                // do not allow saving text region in case when region was checked from list
                // except when in base data region text existed
                unset($data['region_text']);
            }
        } else {
            // do not allow saving region select in case when region was filled as text
            unset($data['region']);
        }

        $event->setData($data);
    }

    /**
     * @param Country $country
     * @return callable
     */
    protected function getRegionClosure(Country $country)
    {
        return function (RegionRepository $regionRepository) use ($country) {
            return $regionRepository->getCountryRegionsQueryBuilder($country);
        };
    }
}
