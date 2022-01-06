<?php

namespace Parameter;

/**
 * Select multiplie user groups.
 * The available groups are automatically detected
 */
final class ParamSpecialGroups extends ParamEnumMulti {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        foreach (\DbEntry\Group::listGroups() as $group) {
            new EnumItem($this, $group->id(), $group->name());
        }

        // set to empty by default
        $this->setValue([]);
    }

    /**
     * Check if a user is covered by the selected groups
     * @param $user A valid object of the requested user
     * @return TRUE when any of the user groups matches to any of the currently selected groups
     */
    public function containsUser(\DbEntry\User $user) {
        $contained_user_ids = explode(";", $this->value());
        return in_array($user->id(), $contained_user_ids);
    }

}
