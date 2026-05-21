<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user account',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Admin username')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Admin full name')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        // Get email
        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Enter admin email: ');
            $email = $helper->ask($input, $output, $question);
        }

        // Get username
        $username = $input->getOption('username');
        if (!$username) {
            $question = new Question('Enter admin username: ');
            $username = $helper->ask($input, $output, $question);
        }

        // Get name
        $name = $input->getOption('name');
        if (!$name) {
            $question = new Question('Enter admin full name: ');
            $name = $helper->ask($input, $output, $question);
        }

        // Get password
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Enter admin password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        // Validate inputs
        if (empty($email) || empty($username) || empty($name) || empty($password)) {
            $io->error('All fields are required!');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('A user with this email already exists!');
            return Command::FAILURE;
        }

        $existingUsername = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUsername) {
            $io->error('A user with this username already exists!');
            return Command::FAILURE;
        }

        // Create admin user
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setName($name);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_STAFF', 'ROLE_USER']);
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user created successfully!');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', $email],
                ['Username', $username],
                ['Name', $name],
                ['Role', 'ROLE_ADMIN'],
            ]
        );

        return Command::SUCCESS;
    }
}

