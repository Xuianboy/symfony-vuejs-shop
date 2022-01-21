<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class AddUserCommand extends Command
{
    protected static $defaultName = 'app:add-user';
    protected static $defaultDescription = 'Create user';
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;
    /**
     * @var UserRepository
     */
    private $userRepository;


    public function __construct(string $name = null , EntityManagerInterface $entityManager, UserPasswordEncoderInterface $encoder, UserRepository $userRepository)
    {
        parent::__construct($name);
        $this->entityManager = $entityManager;
        $this->encoder = $encoder;
        $this->userRepository = $userRepository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('email', 'em',InputArgument::REQUIRED, 'Email')
            ->addOption('password','p' ,InputArgument::REQUIRED, 'Password')
            ->addOption('isAdmin','', InputArgument::OPTIONAL, 'If set the user is created as an administrator', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('add-user-command');


        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $isAdmin = $input->getOption('isAdmin');


        $io->title('Add User Command Wizard');
        $io->text([
           'Please, enter some information'
        ]);

        if(!$email){
            $email = $io->ask('Email');
        }

        if(!$password){
            $password = $io->askHidden('Password (your type will be hidden)');
        }

        if(!$isAdmin){
            $question = new Question('Is Admin? (1 or 0)');
            $isAdmin = $io->askQuestion($question);
        }



        $isAdmin = boolval($isAdmin);

        try{

            $user = $this->createUser($email, $password, $isAdmin);

        } catch(RuntimeException $exception){

            $io->comment($exception->getMessage());

            return Command::FAILURE ;
        }



        $successMessage = sprintf('%s was successfully created: %s',
            $isAdmin ? 'Administrator user' : 'User',
            $email);
        $io->success($successMessage);

        $event = $stopwatch->stop('add-user-command');
        $stopwatchMessage = sprintf('New user\'s id: %s / Elapsed time: %.2f ms / Consumed memory: %.2f MB',
            $user->getId() ,
            $event->getDuration(),
            $event->getMemory() / 1000 / 1000
        );
        $io->comment($stopwatchMessage);
        /*
dd($email, $password, $isAdmin);
        if ($email) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
        */
        return Command::SUCCESS;
    }

    /**
     * @param string $email
     * @param string $password
     * @param bool $isAdmin
     * @return User
     */
    private function createUser(string $email,string $password,bool $isAdmin)
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingUser){
            throw new RuntimeException('User already exists');
        }

        $user = new User();

        $user->setEmail($email);
        $user->setRoles([$isAdmin ? 'ROLE_ADMIN' : 'ROLE_USER' ]);

        $encodedPassword =  $this->encoder->encodePassword($user, $password);
        $user->setPassword($encodedPassword);

        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;

    }

}
