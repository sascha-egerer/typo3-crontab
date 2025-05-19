<?php
declare(strict_types=1);
namespace Helhum\TYPO3\Crontab\Controller;

use Helhum\TYPO3\Crontab\Crontab;
use Helhum\TYPO3\Crontab\Process\ProcessManager;
use Helhum\TYPO3\Crontab\Repository\TaskRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class CrontabModuleController extends ActionController
{
    /**
     * @var TaskRepository
     */
    private $taskRepository;

    /**
     * @var Crontab
     */
    private $crontab;

    /**
     * @var ProcessManager
     */
    private $processManager;

    /**
     * @var ModuleTemplateFactory
     */
    private $moduleTemplateFactory;

    public function __construct(TaskRepository $taskRepository, Crontab $crontab, ModuleTemplateFactory $moduleTemplateFactory, ProcessManager $processManager = null)
    {
        $this->taskRepository = $taskRepository;
        $this->crontab = $crontab;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->processManager = $processManager ?? GeneralUtility::makeInstance(ProcessManager::class, 1);
    }

    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->getDocHeaderComponent()->disable();
        $moduleTemplate->assignMultiple([
            'groupedTasks' => $this->taskRepository->getGroupedTasks(),
            'crontab' => $this->crontab,
            'processManager' => $this->processManager,
            'shortcutLabel' => 'crontab',
            'now' => new \DateTimeImmutable(),
        ]);

        return $moduleTemplate->renderResponse('CrontabModule/List');
    }

    public function toggleScheduleAction(string $identifier): ResponseInterface
    {
        $taskDefinition = $this->taskRepository->findByIdentifier($identifier);
        if ($this->crontab->isScheduled($taskDefinition)) {
            $this->crontab->removeFromSchedule($taskDefinition);
        } else {
            $this->crontab->schedule($taskDefinition);
        }

        return $this->redirect('list');
    }

    public function scheduleForImmediateExecutionAction(array $identifiers): ResponseInterface
    {
        foreach ($identifiers as $identifier) {
            $this->crontab->scheduleForImmediateExecution(
                $this->taskRepository->findByIdentifier($identifier)
            );
        }

        return $this->redirect('list');
    }

    public function terminateAction(string $identifier): ResponseInterface
    {
        $this->processManager->terminateAllProcesses($identifier);
        $this->addFlashMessage(sprintf('Terminated processes for task "%s"', $identifier));

        return $this->redirect('list');
    }
}
