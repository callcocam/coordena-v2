<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ManageRoles = 'role:manage';

    case ViewCongregations = 'congregations:view';
    case ManageCongregations = 'congregations:manage';

    case ViewPublicTalks = 'public-talks:view';
    case ManagePublicTalks = 'public-talks:manage';
    case NotifyPublicTalks = 'public-talks:notify';
}
