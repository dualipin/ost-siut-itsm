<?php

namespace App\Http\Routing\Api;

use App\Http\Actions\Loan\ReviewLoanListAction;
use App\Http\Routing\RouteRegisterInterface;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

class LoanReviewRouteRegister implements RouteRegisterInterface
{
    public function register(Group $group): void
    {
        $group->group('/loan', function (Group $loan) {
            $loan->get('/review', ReviewLoanListAction::class);
        });
    }
}
