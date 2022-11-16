<?php

namespace ParameterSpecial;

/**
 * Select multiplie user groups.
 * The available groups are automatically detected
 */
final class Groups extends \Parameter\ParamEnumMulti {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        foreach (\DbEntry\Group::listGroups() as $group) {
            new \Parameter\EnumItem($this, $group->id(), $group->name());
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
        $contained_group_ids = explode(";", $this->value());
        foreach ($user->groups() as $g) {
            if (in_array($g->id(), $contained_group_ids)) return TRUE;
        }
        return FALSE;
    }

    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
