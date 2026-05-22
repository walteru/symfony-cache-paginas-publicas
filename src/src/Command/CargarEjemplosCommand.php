<?php

namespace App\Command;

use App\Entity\Articulo;
use App\Entity\Suscriptor;
use App\Repository\ArticuloRepository;
use App\Repository\SuscriptorRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cargar-ejemplos', description: 'Carga artículos y suscriptores de ejemplo')]
class CargarEjemplosCommand extends Command
{
    public function __construct(
        private ArticuloRepository $articulos,
        private SuscriptorRepository $suscriptores,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $articulos = [
            ['Cómo empezar con Symfony', 'Borrador recién arrancado, todavía sin enviar a revisión.', 'Walter', 'borrador'],
            ['El componente Messenger explicado', 'Un artículo completo y listo, esperando que un editor lo revise para aprobarlo o pedir cambios.', 'Walter', 'en_revision'],
            ['Sacá el trabajo lento del request', 'Ya revisado y aprobado por el equipo. Publicalo (como editor) y mirá cómo las notificaciones a los suscriptores salen en segundo plano.', 'Walter', 'aprobado'],
        ];

        $ultimoArt = array_key_last($articulos);
        foreach ($articulos as $i => [$titulo, $contenido, $autor, $estado]) {
            $a = new Articulo();
            $a->setTitulo($titulo)->setContenido($contenido)->setAutor($autor)->setEstado($estado);
            $this->articulos->save($a, $i === $ultimoArt);
        }

        // Suscriptores: a cada uno se le manda un mail (lento) al publicar.
        // Cuantos más, más se nota la ventaja de hacerlo fuera del request.
        $emails = ['ada@example.com', 'alan@example.com', 'grace@example.com', 'dennis@example.com', 'claude@example.com'];
        $ultimoSus = array_key_last($emails);
        foreach ($emails as $i => $email) {
            $this->suscriptores->save(new Suscriptor($email), $i === $ultimoSus);
        }

        $io->success(sprintf('%d artículos y %d suscriptores cargados.', count($articulos), count($emails)));

        return Command::SUCCESS;
    }
}
