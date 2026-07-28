<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ExportLessonPackagesSchoolScheduleRequest;
use App\Services\LessonPackages\Export\LessonPackagesSchoolScheduleExportBuilder;
use App\Services\PartnerContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LessonPackageSchoolScheduleExportController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly LessonPackagesSchoolScheduleExportBuilder $exportBuilder,
    ) {
        parent::__construct($partnerContext);
    }

    public function __invoke(ExportLessonPackagesSchoolScheduleRequest $request): StreamedResponse
    {
        $partnerId = $this->requirePartnerId();

        return $this->exportBuilder->download(
            $partnerId,
            $request->dateFrom(),
            $request->dateTo(),
        );
    }
}
