<?php

namespace App\DTO\Mapper;

use App\DTO\Model\FilterDataDTO;

class FilterDataMapper
{
    /**
     * @param array $groupTypes
     * @param array|string[] $dates
     * @param string $locale
     * @return FilterDataDTO
     */
    public static function createFromEntities(array $groupTypes, array $dates, string $locale)
    {
        $filterData = new FilterDataDTO();
        $groupTypeDTOs = [];

        foreach ($groupTypes as $type) {
            $groupTypeDTOs[] = GroupTypeMapper::createGroupTypeFromQueryResult($type, $locale);
        }

        $filterData->setDates($dates);
        $filterData->setGroupTypes($groupTypeDTOs);
        return $filterData;
    }
}
