<?php

namespace Drupal\coyote_img_desc\Helper;

use Coyote\Model\MembershipModel;

class CoyoteMembershipHelper {
  private const ACCEPTED_ROLES = ['editor', 'owner', 'admin'];
  private const WEIGHTED_ROLES = [
    'editor' => 1,
    'owner' => 2,
    'admin' => 3,
  ];

  /** @var MembershipModel[] $memberships */
  public static function getHighestMembershipRole(array $memberships): ?string
  {
    $roles = array_reduce($memberships, function (array $carry, MembershipModel $membershipModel): array
    {
      $role = $membershipModel->getRole();

      if (!in_array($role, self::ACCEPTED_ROLES)) {
        return $carry;
      }

      if (!in_array($role, $carry)) {
        $carry[] = $role;
      }

      return $carry;
    }, []);

    if (empty($roles)) {
      return null;
    }

    $highest = array_reduce($roles, function (int $highest, string $role): int
    {
      $weight = self::WEIGHTED_ROLES[$role];

      if ($weight <= $highest) {
        return $highest;
      }

      return $weight;
    }, 1);

    return self::ACCEPTED_ROLES[$highest - 1];
  }
}