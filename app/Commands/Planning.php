<?php

namespace App\Commands;

use Psr\Container\ContainerInterface;
use App\Command;
use Selective\Config\Configuration;

use Voetbal\Planning as PlanningBase;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Resource\RefereePlaceService;

use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Service as PlanningService;

class Planning extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;


    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function updateSelfReferee(PlanningInput $planningInput)
    {
        $planningService = new PlanningService();
        $planning = $planningService->getBestPlanning($planningInput);
        if ($planning === null) {
            throw new \Exception("there should be a best planning", E_ERROR);
        }

        $planning->setState(PlanningBase::STATE_UPDATING_SELFREFEE);
        $this->planningRepos->save($planning);

        $firstBatch = $planning->getFirstBatch();
        $refereePlaceService = new RefereePlaceService($planning);
        if (!$refereePlaceService->assign($firstBatch)) {
            $this->logger->info("refereeplaces could not be equally assigned");
        }

        $planning->setState(PlanningBase::STATE_SUCCESS);
        $this->planningRepos->save($planning);

        $planningInput->setState(PlanningInput::STATE_ALL_PLANNINGS_TRIED);
        $this->planningInputRepos->save($planningInput);
        $this->logger->info('   update state => STATE_ALL_PLANNINGS_TRIED');
    }
}
