<?php
namespace App\Shared\Provider;

use App\Module\Auth\DTO\UserAuthContextDTO;
use App\Module\Usuario\Repository\UsuarioRepository;
use App\Shared\Context\UserContext;

final readonly class UserContextProvider implements ContextProviderInterface
{
    public function __construct(
        private UserContext $context,
        private UsuarioRepository $repository,
    ) {}

    public function get(): ?UserAuthContextDTO
    {
        $session = $this->context->get();
        if (!$session) {
            return null;
        }
        return $this->repository->findAuthContextById($session->id);
    }
}
