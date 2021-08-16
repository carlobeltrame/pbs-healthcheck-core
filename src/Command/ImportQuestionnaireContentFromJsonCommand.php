<?php

namespace App\Command;

use App\Entity\Aspect;
use App\Entity\Help;
use App\Entity\Question;
use App\Entity\Questionnaire;
use App\Model\CommandStatistics;
use App\Repository\AspectRepository;
use App\Repository\HelpRepository;
use App\Repository\QuestionnaireRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\JsonMachine;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportQuestionnaireContentFromJsonCommand extends StatisticsCommand {

    /**
     * @var EntityManagerInterface $em
     */
    private $em;

    /**
     * @var OutputInterface $output
     */
    private $output;

    /**
     * @var QuestionnaireRepository $questionnaireRepo
     */
    private $questionnaireRepo;

    /**
     * @var AspectRepository $aspectRepo
     */
    private $aspectRepo;

    /**
     * @var QuestionRepository $questionRepo
     */
    private $questionRepo;

    /**
     * @var HelpRepository $helpRepo
     */
    private $helpRepo;

    /**
     * @var string $pathToJson
     */
    private $pathToJson = "imports/questionnaire_imports.json";


    /**
     * @param EntityManagerInterface $em
     * @param QuestionnaireRepository $questionnaireRepo
     * @param AspectRepository $aspectRepo
     * @param QuestionRepository $questionRepo
     * @param HelpRepository $helpRepo
     */
    public function __construct(
        EntityManagerInterface $em,
        QuestionnaireRepository $questionnaireRepo,
        AspectRepository $aspectRepo,
        QuestionRepository $questionRepo,
        HelpRepository $helpRepo
    ) {
        parent::__construct();

        $this->em = $em;
        $this->questionnaireRepo = $questionnaireRepo;
        $this->aspectRepo = $aspectRepo;
        $this->questionRepo = $questionRepo;
        $this->helpRepo = $helpRepo;
    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        $this->output = $output;

        $this->output->writeln("Starting import of questionnaires...");

        if (!file_exists($this->pathToJson)) {
            $this->output->writeln("No data to import. File at ".$this->pathToJson." not found.");
            return 1;
        }

        $questionnaires = JsonMachine::fromFile($this->pathToJson);
        foreach ($questionnaires as $questionnaire) {
            $this->importQuestionnaire($questionnaire);
        }

        $this->output->writeln("Questionnaire import process has finished.");
        $this->output = null;
        return 0;
    }

    protected function configure() {
        $this
            ->setName("app:import-questionnaire-data");
    }

    /**
     * @param $questionnaire
     */
    private function importQuestionnaire($questionnaire): void
    {
        $db_questionnaire = $this->questionnaireRepo->findOneBy(["id" => $questionnaire["id"]]);

        if (!$db_questionnaire) {
            $db_questionnaire = new Questionnaire();
        }

        $db_questionnaire->setType($questionnaire["type"]);

        $aspects = $questionnaire["aspects"];

        $this->em->persist($db_questionnaire);
        $this->em->flush();

        foreach ($aspects as $aspect) {
            $isDeprecated = false;
            if (array_key_exists("deprecated", $aspect)) {
                $isDeprecated = $aspect["deprecated"];
            }

            $this->importAspect($aspect, $db_questionnaire, $isDeprecated);
        }
    }

    private function importAspect($aspect, Questionnaire $questionnaire, $isDeprecated = false) {
        $db_aspect = $this->aspectRepo->findOneBy([
            "questionnaire" => $questionnaire->getId(),
            "local_id" => $aspect["id"]
        ]);

        if (!$db_aspect) {
            $db_aspect = new Aspect();
            $db_aspect->setCreatedAt(new \DateTimeImmutable("now"));
            $db_aspect->setLocalId($aspect["id"]);
        }

        $db_aspect->setNameDe($aspect["name_de"]);
        $db_aspect->setNameFr($aspect["name_fr"]);
        $db_aspect->setNameIt($aspect["name_it"]);
        $db_aspect->setQuestionnaire($questionnaire);

        if ($isDeprecated) {
            $db_aspect->setDeletedAt(new \DateTimeImmutable("now"));
        }

        $this->em->persist($db_aspect);
        $this->em->flush();

        $questions = $aspect["questions"];

        foreach ($questions as $question) {

            $questionIsDeprecated = $isDeprecated;
            if (array_key_exists("deprecated", $question)) {
                $questionIsDeprecated = $question["deprecated"];
            }

            $this->importQuestion($question, $db_aspect, $questionIsDeprecated);
        }
    }

    private function importQuestion($question, Aspect $aspect, $isDeprecated){
        $db_question = $this->questionRepo->findOneBy([
            "aspect" => $aspect->getId(),
            "local_id" => $question["id"]
        ]);

        if (!$db_question) {
            $db_question = new Question();
            $db_question->setAnswerOptions($question["answer_options"]);
            $db_question->setCreatedAt(new \DateTimeImmutable("now"));
            $db_question->setLocalId($question["id"]);
        }

        $db_question->setQuestionDe($question["question_de"]);
        $db_question->setQuestionFr($question["question_fr"]);
        $db_question->setQuestionIt($question["question_it"]);
        $db_question->setAspect($aspect);

        if ($isDeprecated) {
            $db_question->setDeletedAt(new \DateTimeImmutable("now"));
        }

        $this->em->persist($db_question);
        $this->em->flush();

        $help = $question["help"];

        foreach ($help as $helpItem) {
            $helpItemIsDeprecated = $isDeprecated;
            if (array_key_exists("deprecated", $helpItem)) {
                $helpItemIsDeprecated = $helpItem["deprecated"];
            }

            $this->importHelp($helpItem, $db_question, $helpItemIsDeprecated);
        }
    }

    private function importHelp($helpItem, Question $question, $isDeprecated) {
        $db_help = $this->helpRepo->findOneBy([
            "question" => $question->getId(),
            "local_id" => $helpItem["id"]
        ]);

        if (!$db_help) {
            $db_help = new Help();
            $db_help->setCreatedAt(new \DateTimeImmutable("now"));
            $db_help->setLocalId($helpItem["id"]);
        }

        $db_help->setHelpDe($helpItem["help_de"]);
        $db_help->setHelpFr($helpItem["help_fr"]);
        $db_help->setHelpIt($helpItem["help_it"]);
        $db_help->setSeverity($helpItem["severity"]);
        $db_help->setQuestion($question);

        if ($isDeprecated) {
            $db_help->setDeletedAt(new \DateTimeImmutable("now"));
        }

        $this->em->persist($db_help);
        $this->em->flush();
    }

    public function getStats(): CommandStatistics {
        return new CommandStatistics(0, '');
    }
}