<?php

declare(strict_types=1);

namespace Dranzd\StorebunkPos\Domain\Model\PosSession\ValueObject;

use Dranzd\Common\Domain\ValueObject\Identity\Uuid;

/**
 * Operator of a POS session, expressed in this module's own ubiquitous language.
 *
 * Deliberately a module-owned identity (a cashier), NOT the host's User id: the
 * package is a bounded context and must not depend on an outside identity system.
 * The host maps its user -> cashier at the boundary.
 *
 * This is distinct from the command/event actor metadata (ActorCapable's
 * `_actor_id`), which carries the host User performing the action. A session
 * started by host user 0005 acting as cashier 1 records `cashier_id = 1` as the
 * domain operator while the actor metadata independently carries user 0005.
 */
final class CashierId extends Uuid
{
}
