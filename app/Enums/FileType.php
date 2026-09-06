<?php

namespace App\Enums;

enum FileType: string
{
    case ReportPdf = 'report_pdf';
    case Notebook = 'notebook';
    case ArchiveZip = 'archive_zip';
    case Presentation = 'presentation';
    case Readme = 'readme';
    case Visualization = 'visualization';
    case DatasetCsv = 'dataset_csv';
    case Other = 'other';
}
