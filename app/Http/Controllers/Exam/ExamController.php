<?php

namespace App\Http\Controllers\Exam;

use PDF;
use App\Models\User;
use App\Models\Course;
use App\Models\Program;
use App\Models\Classroom;
use App\Models\StudyLevel;
use App\Models\CourseGrade;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Models\SemesterGrade;
use App\Models\SystemSetting;
use App\Models\ClassroomStudent;
use App\Models\InstituteSetting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\MainController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class ExamController extends MainController
{
    /**
     * Handling Views
     */
    public function transcript(Request $request){
        // If authenticated user is a student.
        if(Auth::user()->role == 'student'){
            // Check if student is associated with a class
            if(ClassroomStudent::where('users_username', Auth::user()->username)){
                return redirect()->route('transcript.student', [Auth::user()->username]);
            }else{
                abort(404, 'Pelajar tidak berada dalam mana-mana kelas!');
            }
        }elseif(Gate::allows('authAdmin') || Gate::allows('authLecturer')){
             // If authenticated user is a student.
            return view('dashboard.exam.addBulk')->with(['settings' => $this->instituteSettings, 'page' => 'Tambah Transkrip']);
        }else{
            return redirect()->route('dashboard');
        }
    }
    public function semesterView($studentID){
        if(User::where('username', $studentID)->where('role', 'student')->first()){
            // Only student itself, coordinators and admin can view
            if(Gate::allows('authUser', $studentID) || Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
                $semesterGrades = SemesterGrade::select('study_levels_code', 'semester')->where('users_username', $studentID)->get();
                return view('dashboard.exam.view')->with(['settings' => $this->instituteSettings, 'page' => 'Senarai Transkrip', 'semesterGrades' => $semesterGrades, 'studentID' => $studentID]);
            }else{
                abort(403, 'Anda tiada akses pada laman ini!');
            }
        }else{
            abort(404, 'Pelajar tidak dijumpai!');
        }
    }
    public function transcriptView($studentID, $studyLevel, $semester){
        if(User::where('username', $studentID)->where('role', 'student')->first()){
            // Only student itself, coordinators and admin can view
            if(Gate::allows('authUser', $studentID) || Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
                // Check if transcript exist
                if(SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->first()){
                    if(ClassroomStudent::where('users_username', $studentID)->first()){
                        $studentClassroom = ClassroomStudent::where('users_username', $studentID)->first()->classroom;
                        $studentProgram = Program::where('code', $studentClassroom->programs_code)->first()->name;
                        $studyLevelName = StudyLevel::where('code', $studyLevel)->first()->name;
                        $semesterGrade = SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->first();
                        // Student Details
                        $studentName = User::select('fullname')->where('username', $studentID)->first()->fullname;
                        if(UserProfile::select('identification_number')->where('users_username', $studentID)->first() != null){
                            $studentIdentificationNumber = UserProfile::select('identification_number')->where('users_username', $studentID)->first()->identification_number;
                        }else{
                            $studentIdentificationNumber = "N/A";
                        }
                        $studentDetails = [
                            'name' => $studentName,
                            'identificationNumber' => $studentIdentificationNumber,
                            'matrixNumber' => $studentID
                        ];
                        $courseGrades = CourseGrade::join('courses', 'course_grades.courses_code', 'courses.code')->select('course_grades.credit_hour', 'course_grades.grade_pointer', 'courses.code', 'courses.name')->where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->get();
                        return view('dashboard.exam.transcript')->with(['settings' => $this->instituteSettings, 'page' => 'Transkrip Penilaian', 'studentProgram' => $studentProgram, 'studyLevelName' => $studyLevelName, 'semester' => $semester, 'studentDetails' => $studentDetails, 'courseGrades' => $courseGrades, 'semesterGrade' => $semesterGrade]);
                    }else{
                        abort(404, 'Pelajar tidak diletakkan dalam kelas!');
                    }
                }else{
                    abort(404, 'Transkrip tidak dijumpai!');
                }
            }else{
                abort(403, 'Anda tiada akses pada laman ini!');
            }
        }else{
            abort(404, 'Pelajar tidak dijumpai!');
        }
    }
    public function transcriptAddView($studentID){
        // Only coordinator and admin can add transcript
        if(Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
            if(User::where('username', $studentID)->first()){
                if(User::where('username', $studentID)->first()->role == 'student'){
                    // Student Details
                    $studentName = User::select('fullname')->where('username', $studentID)->first()->fullname;
                    if(UserProfile::select('identification_number')->where('users_username', $studentID)->first() != null){
                        $studentIdentificationNumber = UserProfile::select('identification_number')->where('users_username', $studentID)->first()->identification_number;
                    }else{
                        $studentIdentificationNumber = "N/A";
                    }
                    $studentDetails = [
                        'name' => $studentName,
                        'identificationNumber' => $studentIdentificationNumber,
                        'matrixNumber' => $studentID
                    ];
                    $studyLevels = StudyLevel::select('code', 'name', 'total_semester')->get();
                    $maxSemester = StudyLevel::select('total_semester')->get()->max()['total_semester'];
                    return view('dashboard.exam.add')->with(['settings' => $this->instituteSettings, 'page' => 'Tambah Transkrip', 'studyLevels' => $studyLevels, 'maxSemester' => $maxSemester, 'studentDetails' => $studentDetails]);
                }else{
                    abort(404, 'Pengguna bukanlah seorang pelajar!');
                }
            }else{
                abort(404, 'Pengguna tidak dijumpai!');
            }
        }else{
            abort(403, 'Anda tiada akses pada laman ini!');
        }
    }
    public function transcriptUpdateView($studentID, $studyLevel, $semester){
        if(Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
            if(User::where('username', $studentID)->first()){
                if(User::where('username', $studentID)->first()->role == 'student'){
                    // Check if transcript is available (with SemesterGrade and CourseGrade if both existed)
                    $semesterGrade = SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->first();
                    $courseGrades = CourseGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->get();
                    if($semesterGrade){
                        // Student Details
                        $studentName = User::select('fullname')->where('username', $studentID)->first()->fullname;
                        if(UserProfile::select('identification_number')->where('users_username', $studentID)->first() != null){
                            $studentIdentificationNumber = UserProfile::select('identification_number')->where('users_username', $studentID)->first()->identification_number;
                        }else{
                            $studentIdentificationNumber = "N/A";
                        }
                        $studentDetails = [
                            'name' => $studentName,
                            'identificationNumber' => $studentIdentificationNumber,
                            'matrixNumber' => $studentID
                        ];
                        $studyLevel = StudyLevel::where('code', $studyLevel)->select('code', 'name')->first();
                        $maxSemester = StudyLevel::select('total_semester')->get()->max()['total_semester'];
                        return view('dashboard.exam.update')->with(['settings' => $this->instituteSettings, 'page' => 'Kemaskini Transkrip', 'studyLevel' => $studyLevel, 'studentID' => $studentID, 'semester' => $semester, 'semesterGrade' => $semesterGrade, 'courseGrades' => $courseGrades, 'studentDetails' => $studentDetails]);
                    }else{
                        abort(404, 'Tiada transkrip dijumpai!');
                    }
                }else{
                    abort(404, 'Pengguna bukanlah seorang pelajar!');
                }
            }else{
                abort(404, 'Pengguna tidak dijumpai!');
            }
        }else{
            abort(403, 'Anda tiada akses pada laman ini!');
        }
    }
    public function downloadSpreadsheetTemplate(Request $request){
        $fileName = "Templat_Transkrip_Semester_eKV.xlsx";
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator("eKV")
            ->setTitle("Templat_Transkrip_Semester_eKV");
        $sheet = $spreadsheet->getActiveSheet();
        // Header
        $fontStyleHeader = [
            'font' => [
                'size' => 16,
                'name' => 'Arial',
                'bold' => true,
                'color' => ['argb' => '00000000'],
            ]
        ];
        $sheet->setCellValue('A5', 'eKV - Templat Penambahan Transkrip Semester Pelajar');
        $spreadsheet->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A5:E5');
        $spreadsheet->getActiveSheet()->getStyle('A5')->applyFromArray($fontStyleHeader);
        $spreadsheet->getActiveSheet()->getRowDimension('5')->setRowHeight(35);

        // Data fields
        $commonCellOne = ['A8', 'B8', 'F11', 'A13', 'B13', 'C13', 'D13', 'E13', 'F13', 'G13',
        "H11","I11","J11","K11","L11","M11","N11","O11","P11","Q11","R11","S11","T11","U11","V11","W11","X11","Y11",
        "H13","I13","J13","K13","L13","M13","N13","O13","P13","Q13","R13","S13","T13","U13","V13","W13","X13","Y13"];
        $fontStyleData = [
            'font' => [
                'size' => 11,
                'name' => 'Arial',
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ]
        ];
        for ($i=0; $i < count($commonCellOne); $i++) { 
            $spreadsheet->getActiveSheet()->getStyle($commonCellOne[$i])->applyFromArray($fontStyleData);
            $spreadsheet->getActiveSheet()->getStyle($commonCellOne[$i])->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $spreadsheet->getActiveSheet()->getStyle($commonCellOne[$i])->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $spreadsheet->getActiveSheet()->getStyle($commonCellOne[$i])->getFill()->getStartColor()->setARGB('111111');
        }

        // Column width
        $columnLetter = "A,B,C,D,E";
        $columnLetters = explode(",", $columnLetter);
        for ($i=0; $i < count($columnLetters); $i++) { 
            $spreadsheet->getActiveSheet()->getColumnDimension($columnLetters[$i])->setWidth(30, 'pt'); 
        }

        $columnLetter = "F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y";
        $columnLetters = explode(",", $columnLetter);
        for ($i=0; $i < count($columnLetters); $i++) { 
            $spreadsheet->getActiveSheet()->getColumnDimension($columnLetters[$i])->setWidth(12, 'pt'); 
        }

        $sheet->setCellValue('A8', 'Semester');
        $sheet->setCellValue('B8', 'Tahap Pengajian');
        
        $mergeCells = ["F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y"];
        for ($i=0; $i < 20;) { 
            $rowFirst = $mergeCells[$i] . "11" . ":" . $mergeCells[$i + 1] . "11";
            $sheet->setCellValue($mergeCells[$i] . "11", 'KOD KURSUS');
            $rowSecond = $mergeCells[$i] . "12" . ":" . $mergeCells[$i + 1] . "12";
            $sheet->setCellValue($mergeCells[$i] . "13", 'Nilai Gred');
            $sheet->setCellValue($mergeCells[$i + 1] . "13", 'Jam Kredit');
            $sheet->mergeCells($rowFirst);
            $sheet->mergeCells($rowSecond);
            $i += 2;
        }

        $sheet->setCellValue('A13', 'ID Pelajar');
        $sheet->setCellValue('B13', 'Purata Nilai Gred Semasa');
        $sheet->setCellValue('C13', 'Jumlah Nilai Kredit Semasa');
        $sheet->setCellValue('D13', 'Purata Nilai Gred Kumulatif');
        $sheet->setCellValue('E13', 'Jumlah Nilai Kredit Kumulatif');

        /**
         * Alignments
         */
        // Horizontal Alignment One By One
        $horizontalCells = ["A9", "B9", "F12", "H12", "J12", "L12", "N12", "P12", "R12", "T12", "V12", "X12"];
        foreach ($horizontalCells as $value) {
            $spreadsheet->getActiveSheet()->getStyle($value)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        // Horizontal Alignment For Range
        $spreadsheet->getActiveSheet()->getStyle('A14:Y43')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle('A1:Y43')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER); // Vertical Alignment For *All Cells

        // Borders
        $spreadsheet->getActiveSheet()->getStyle('A8:B9')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $spreadsheet->getActiveSheet()->getStyle('F11:Y43')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $spreadsheet->getActiveSheet()->getStyle('A13:G43')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        $writer->save('php://output');
        exit();
    }
    /**
     * Handling POST Requests
     */
    public function transcriptAddUpdate(Request $request, $studentID){
        // Only coordinator and admin could update. 
        if(Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
            // Check if user exist.
            if(User::where('username', $studentID)->first()){
                // Check if user is a student
                if(User::where('username', $studentID)->first()->role == 'student'){
                    $validated = $request->validate([
                        'studyLevel' => ['required'],
                        'semester' => ['required'],
                        'total_credit_gpa' => ['required', 'numeric'],
                        'total_credit_cgpa' => ['required', 'numeric'],
                        'gpa' => ['required', 'numeric'],
                        'cgpa' => ['required', 'numeric']
                    ]);
                    $studyLevel = $request->studyLevel;
                    $semester = $request->semester;
                    $creditGPA = $request->total_credit_gpa;
                    $creditCGPA = $request->total_credit_cgpa;
                    // Check if GPA and CGPA have a dot in it (meaning it's a float). If not add two '0' digits
                    if(strlen($request->gpa) > 4){
                        $pointerGPA = substr($request->gpa, 0, 4);
                    }else{
                        $pointerGPA = $request->gpa;
                    }
                    if(strlen($request->cgpa) > 4){
                        $pointerCGPA = substr($request->cgpa, 0, 4);
                    }else{
                        $pointerCGPA = $request->cgpa;
                    }
                    if(strpos($pointerGPA, ".")){
                        // Check if GPA and CGPA have 2 digits on the end, if not add a '0'
                        $gpa = explode('.', $pointerGPA);
                        if(strlen($gpa[1]) < 2){
                            $gpa = $pointerGPA . '0';
                        }else{
                            $gpa = $pointerGPA;
                        }
                    }else{
                        $gpa = $request->gpa . '.00';
                    }
                    if(strpos($pointerCGPA, ".")){
                        // Check if GPA and CGPA have 2 digits on the end, if not add a '0'
                        $cgpa = explode('.', $pointerCGPA);
                        if(strlen($cgpa[1]) < 2){
                            $cgpa = $pointerCGPA . '0';
                        }else{
                            $cgpa = $pointerCGPA;
                        }
                    }else{
                        $cgpa = $pointerCGPA . '.00';
                    }
                    // Because it's not neccessary to add multiple method for add and update. I'm just gonna use the same method for both actions.
                    // For this, student semester grade and all course grades will be deleted before inserting new ones
                    SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->delete();
                    CourseGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->delete();
                    // Add semester grade
                    $coursesCodes = $request->coursesCode;
                    $creditHours = $request->creditHour;
                    $gradePointers = $request->gradePointer;
                    $allData = array();
                    // Check if course grades is inserted, if not return an error
                    if(isset($coursesCodes)){
                        SemesterGrade::updateOrCreate(
                            ['users_username' => $studentID, 'study_levels_code' => $studyLevel, 'semester' => $semester],
                            ['total_credit_gpa' => $creditGPA, 'total_credit_cgpa' => $creditCGPA, 'gpa' => $gpa, 'cgpa' => $cgpa]
                        );
                        for($i=0; $i < count($coursesCodes); $i++){
                            // Check if course is existed
                            if(Course::where('code', $coursesCodes[$i])->first()){
                                if(strlen($gradePointers[$i]) > 4){
                                    $pointer = substr($gradePointers[$i], 0, 4);
                                }else{
                                    $pointer = $gradePointers[$i];
                                }
                                // Check if gradePointer have a dot in it (meaning it's a float). If not add two '0' digits
                                if(strpos($pointer, ".")){
                                    // Check if gradePointer have 2 digits on the end, if not add a '0'
                                    $gradePointer = explode('.', $pointer);
                                    if(strlen($gradePointer[1]) < 2){
                                        $gradePointer = $pointer . '0';
                                    }else{
                                        $gradePointer = $pointer;
                                    }
                                }else{
                                    $gradePointer = $pointer . '.00';
                                }
                                $allData[] = array('users_username' => strtolower($studentID), 'study_levels_code' => $studyLevel, 'semester' => $semester, 'courses_code' => strtolower($coursesCodes[$i]), 'credit_hour' => $creditHours[$i], 'grade_pointer' => $gradePointer);
                            }
                        }
                        // I have to make custom upsert function. Laravel upsert() doesn't
                        // seems to like 'semester' column without unique index.
                        foreach($allData as $data){
                            if(CourseGrade::where('users_username', $data['users_username'])->where('study_levels_code', $data['study_levels_code'])->where('semester', $data['semester'])->where('courses_code', $data['courses_code'])->count() < 1){
                                CourseGrade::create(
                                    $data
                                );
                            }else{
                                CourseGrade::where('users_username', $data['users_username'])
                                ->where('study_levels_code', $data['study_levels_code'])
                                ->where('semester', $data['semester'])
                                ->where('courses_code', $data['courses_code'])
                                ->update(['credit_hour' => $data['credit_hour'], 'grade_pointer' => $data['grade_pointer']]);
                            }
                        }
                        session()->flash('transcriptSuccess', 'Transkrip Berjaya Dikemas kini!');
                        return redirect()->back();
                    }else{
                        return redirect()->back()->withInput()->withErrors([
                            'noCourseInserted' => 'Tiada rekod gred kursus ditambah!'
                        ]);
                    }
                }else{
                    abort(404, 'Pengguna bukanlah seorang pelajar!');
                }
            }else{
                abort(404, 'Pengguna tidak dijumpai!');
            }
        }else{
            abort(403, 'Anda tiada akses pada laman ini!');
        }
    }
    public function transcriptBulkAdd(Request $request){
        /**
         * For adding student's exam transcript in bulk using Excel spreadsheet
         * Each student's transcript is seperated by sheets
         * Maximum of 10 courses result can be added 
         * Data after an empty cell will not be added to the array of transcript to add(check by ID pelajar and Kod Kursus)
         * If there's a duplicated data, for example duplicated ID Pelajar and Kod Kursus, only the first data will be added.
         */

        // Check if spreadsheet file have .XLSX extension
        $excelErr = [];
        $excelStat = [];
        $semesterGradesAddArray = [];
        $courseGradesAddArray = [];
        if($request->spreadsheet->extension() == 'xlsx'){
            // Check for empty cells (Semester: A9, Tahap Pengajian: B9)
            $userXLSX = $request->file('spreadsheet');
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            //Read XLSX file.
            $spreadsheet = $reader->load($userXLSX);
            $sheet = $spreadsheet->getActiveSheet();
            if($sheet->getCell("A9")->getValue() === NULL){
                array_push($excelErr, '[A9] Sel Semester kosong!');
            }else{
                // Check if Tahap Pengajian empty
                if($sheet->getCell("A9")->getValue() === NULL){
                    array_push($excelErr, '[B9] Sel Tahap Pengajian kosong!');
                }else{
                    // Check if Tahap Pengajian exist
                    if(!StudyLevel::where('code', strtolower($sheet->getCell("B9")->getValue()))->first()){
                        array_push($excelErr, '[B9] Tahap Pengajian tidak wujud!');
                    }else{
                        // Check if Tahap Pengajian have amount based on the sheet
                        $totalSemester = StudyLevel::select('total_semester')->where('code', strtolower($sheet->getCell('B9')->getValue()))->first();
                        if($sheet->getCell("A9")->getValue() < 1 || $sheet->getCell("A9")->getValue() > $totalSemester['total_semester']){
                            array_push($excelErr, '[A9] Semester tidak tergolong pada jumlah semester Tahap Pengajian!');
                        }else{
                            $generalSemester = $sheet->getCell("A9")->getValue();
                            $generalStudyLevel = strtolower($sheet->getCell("B9")->getValue());
                            // Check is first Pelajar ID empty (needs atleast 1 student transcript)
                            if($sheet->getCell("A14")->getValue() === NULL){
                                array_push($excelErr, '[A14] Sel ID Pelajar kosong!');
                            }else{
                                // Check if student list available
                                $studentListAvailable = []; 
                                for ($i=14; $i < 44; $i++) { 
                                    if($sheet->getCell("A" . $i)->getValue() === NULL){
                                        break;
                                    }else{
                                        $studentListAvailable[$i] = $sheet->getCell("A" . $i)->getValue();
                                    }
                                }
                                // Check for duplicated ID. If there's a duplicated ID, only the first one will be added to the list.
                                $studentListNoDuplicate = array_unique($studentListAvailable);
                                // Check is first Kod Kursus empty (needs atleast 1 course code)
                                if($sheet->getCell("F12")->getValue() === NULL){
                                    array_push($excelErr, '[F12] Sel Kod Kursus kosong!');
                                }else{
                                    $mergeCells = ["F12", "H12", "J12", "L12", "N12", "P12", "R12", "T12", "V12", "X12"];
                                    $courseListAvailable = [];
                                    $courseList = [];
                                    for ($i=0; $i < count($mergeCells); $i++) { 
                                        $code = $sheet->getCell($mergeCells[$i])->getValue();
                                        $courseList[$mergeCells[$i]] = $code;
                                    }
                                    // Check for NULL cell. All cell after the NULL will be ignored.
                                    foreach ($courseList as $courseListKey => $courseListValue) {
                                        if($courseList[$courseListKey] === NULL){
                                            break;
                                        }else{
                                            $courseListAvailable[$courseListKey] = $courseListValue;
                                        }
                                    }
                                    $courseListNoDuplicate = array_unique($courseListAvailable);
                                    // Check if student exist
                                    $studentListValid = [];
                                    foreach ($studentListNoDuplicate as $key => $value) {
                                        if(!User::where('username', strtolower($studentListAvailable[$key]))->first()){
                                            array_push($excelErr, '[' . "A" . $key . '] Pelajar tidak wujud!');
                                        }else{
                                            $studentListValid[$key] = $value;
                                        }
                                    }
                                    // Check if course exist
                                    $courseListValid = [];
                                    foreach ($courseListNoDuplicate as $key => $value) {
                                        if(!Course::where('code', strtolower($value))->first()){
                                            array_push($excelErr, '[' . $key . '] Kursus tidak wujud!');
                                        }else{
                                            $courseListValid[preg_replace('/[^a-zA-Z]/', '', $key)] = $value;
                                        }
                                    }
                                    // Display error for unvalid ID Pelajar and Kod Kursus
                                    if(count($excelErr) > 0){
                                        $request->session()->flash('excelErr', $excelErr);
                                        return redirect()->back();
                                    }else{
                                        // Check for empty data on gpa [B], creditHour [C], cgpa [D], cCreditHour [E]
                                        // It's gonna take a long time to implement each check, so I do it all at the same time

                                        $studentSemesterGrade = [];
                                        foreach ($studentListValid as $key => $value) {
                                            if($sheet->getCell("B" . $key)->getValue() === NULL || $sheet->getCell("C" . $key)->getValue() === NULL || $sheet->getCell("D" . $key)->getValue() === NULL || $sheet->getCell("E" . $key)->getValue() === NULL){
                                                array_push($excelErr, '[' . 'Baris ' . $key . '] Pastikan kesemua ruangan diisi!');
                                            }else{
                                                // Check if value is integer, add .00 behind it for column B
                                                if(is_int($sheet->getCell("B" . $key)->getValue())){
                                                    $bValue = $sheet->getCell("B" . $key)->getValue() . ".00";
                                                }elseif(is_float($sheet->getCell("B" . $key)->getValue())){
                                                    // Add 0 to decimal place if less than 10
                                                    $dividedB = explode(".", $sheet->getCell("B" . $key)->getValue());
                                                    if(strlen($dividedB[1]) < 2){
                                                        $bValue = $dividedB[0] . "." . $dividedB[1] . "0";
                                                    }else{
                                                        $bValue = $sheet->getCell("B" . $key)->getValue();
                                                    }
                                                }
                                                // Check if value is integer, add .00 behind it for column D
                                                if(is_int($sheet->getCell("D" . $key)->getValue())){
                                                    $dValue = $sheet->getCell("D" . $key)->getValue() . ".00";
                                                }elseif(is_float($sheet->getCell("D" . $key)->getValue())){
                                                    // Add 0 to decimal place if less than 10
                                                    $dividedD = explode(".", $sheet->getCell("D" . $key)->getValue());
                                                    if(strlen($dividedD[1]) < 2){
                                                        $dValue = $dividedD[0] . "." . $dividedD[1] . "0";
                                                    }else{
                                                        $dValue = $sheet->getCell("D" . $key)->getValue();
                                                    }
                                                }
                                                $studentID = $value;
                                                $studyLevel = strtolower($sheet->getCell("B9")->getValue());
                                                $semester = $sheet->getCell("A9")->getValue();
                                                $currentCreditHour = $sheet->getCell("C" . $key)->getValue();
                                                $cumulativeCreditHour = $sheet->getCell("E" . $key)->getValue();
                                                //studentID, bValue [gpa], currentCreditHour, dValue [cgpa], cumulativeCreditHour
                                                // Add student semester grade
                                                array_push($semesterGradesAddArray, ['total_credit_gpa' => $currentCreditHour, 'total_credit_cgpa' => $cumulativeCreditHour, 'gpa' => $bValue, 'cgpa' => $dValue]);
                                            }
                                        }
                                    }
                                    $courseListWithNumber = [];
                                    $courseColumnList = ["F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y"];
                                    foreach ($courseListValid as $key => $value) {
                                        $currentColumnIndex = array_search($key, $courseColumnList);
                                        $neighborColumnIndex = $currentColumnIndex + 1;
                                        for ($i=0; $i < count($courseColumnList); $i++) { 
                                            $neighborColumn = $courseColumnList[$neighborColumnIndex];
                                        }
                                        $gradePointerColumn = $key;
                                        $creditHourColumn = $neighborColumn;
                                        foreach ($studentListValid as $k => $v) {
                                            array_push($courseListWithNumber, [$gradePointerColumn . $k, $creditHourColumn . $k]);
                                        }
                                    }
                                    foreach ($courseListWithNumber as $value) {
                                        $row = $value[0];
                                        $courseCreditHour = $sheet->getCell($value[0])->getValue();
                                        $courseGradePointer = $sheet->getCell($value[1])->getValue();
                                        // Check if value is integer, add .00 behind it for column D
                                        if(is_int($courseGradePointer)){
                                            $courseGradePointer = $courseGradePointer . ".00";
                                        }elseif(is_float($courseGradePointer)){
                                            // Add 0 to decimal place if less than 10
                                            $dividedD = explode(".", $courseGradePointer);
                                            if(strlen($dividedD[1]) < 2){
                                                $courseGradePointer = $dividedD[0] . "." . $dividedD[1] . "0";
                                            }else{
                                                $courseGradePointer = $courseGradePointer;
                                            }
                                        }
                                        array_push($courseGradesAddArray, ['row' => $row,'credit_hour' => $courseCreditHour, 'grade_pointer' => $courseGradePointer]);
                                    }
                                    // Check if coordinator to the student or an admin
                                    foreach ($studentListValid as $key => $value) {
                                        $studentID = $value;
                                        if(Gate::allows('authAdmin') || Gate::allows('authCoordinator', $studentID)){
                                            $details = ['users_username' => $studentID, 'study_levels_code' => $generalStudyLevel, 'semester' => $generalSemester];
                                            foreach ($semesterGradesAddArray as $value) {
                                                SemesterGrade::updateOrCreate($details, $value);
                                            }
                                            foreach ($courseGradesAddArray as $value) {
                                                $row = preg_replace('/[^0-9]/', '', $value['row']);
                                                $column = preg_replace('/[^a-zA-Z]/', '', $value['row']);
                                                $courseCode =$sheet->getCell($column . "12")->getValue();
                                                $courseDetails = ['users_username' => strtolower($sheet->getCell("A" . $row)->getValue()), 'courses_code' => $courseCode, 'study_levels_code' => $generalStudyLevel, 'semester' => $generalSemester];
                                                $courses = ['credit_hour' => $value['credit_hour'], 'grade_pointer' => $value['grade_pointer']];
                                                CourseGrade::updateOrCreate($courseDetails, $courses);
                                            }
                                            array_push($excelStat, '[A' . $key .'] Gred Semester berjaya ditambah!');
                                        }else{
                                            array_push($excelErr, '[' . 'A' . $key . '] Anda tidak mempunyai kebenaran untuk menambah transkrip bagi pelajar ini!');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }else{
            array_push($excelErr, 'Hanya file jenis XLSX sahaja yang disokong!');
        }
        $request->session()->flash('excelErr', $excelErr);
        $request->session()->flash('excelStat', $excelStat);
        return redirect()->back();
    }
    public function transcriptRemove(Request $request){
        // Only coordinator and admin could remove.
        if(Gate::allows('authCoordinator', $request->studentID) || Gate::allows('authAdmin')){
            // Check if user exist.
            if(User::where('username',  $request->studentID)->first()){
                // Check if user is a student
                if(User::where('username', $request->studentID)->first()->role == 'student'){ 
                    $studentID = $request->studentID;
                    $studyLevel = $request->studyLevel;
                    $semester = $request->semester;
                    // Remove SemesterGrade and all CourseGrades
                    SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->delete();
                    CourseGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->delete();
                    session()->flash('transcriptDeleteSuccess', 'Transkrip berjaya dibuang!');
                    return redirect()->back();
                }else{
                    abort(404, 'Pengguna bukanlah seorang pelajar!');
                }
            }else{
                abort(404, 'Pengguna tidak dijumpai!');
            }
        }else{
            abort(403, 'Anda tiada akses pada laman ini!');
        }
    }
    public function transcriptDownload(Request $request, $studentID, $studyLevel, $semester){
        // Only the student, coordinator and admin could download. 
        if(Gate::allows('authUser', $studentID) || Gate::allows('authCoordinator', $studentID) || Gate::allows('authAdmin')){
            // Check if user exist.
            if(User::where('username', $studentID)->first()){
                // Check if user is a student
                if(User::where('username', $studentID)->first()->role == 'student'){
                    // Check if student is added into a classroom.
                    if(ClassroomStudent::where('users_username', $studentID)->first()){
                        error_reporting(E_ERROR);
                        // Check if transcript exist based on semester grade table.
                        if(SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->first()){
                            $studyLevelName = StudyLevel::where('code', $studyLevel)->first()->name;
                            $studentName = User::where('username', $studentID)->first()->fullname;
                            $title = ucwords($studentName) . " - Transkrip Semester {$semester}  {$studyLevelName}";
                            $studentClassroom = ClassroomStudent::where('users_username', $studentID)->first()->classroom;
                            if(Storage::disk('local')->exists('public/img/system/logo-300.png')){
                                $collegeImageUrl = 'public/img/system/logo-300.png';
                            }elseif(Storage::disk('local')->exists('public/img/system/logo-def-300.png')){
                                $collegeImageUrl = 'public/img/system/logo-def-300.png';
                            }else{
                                $collegeImageUrl = '';  
                            }
                            $studentProgram = Program::where('code', $studentClassroom->programs_code)->first()->name;
                            $studyLevelName = StudyLevel::where('code', $studyLevel)->first()->name;
                            $semesterGrade = SemesterGrade::where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->first();
                            // Student Details
                            $studentName = User::select('fullname')->where('username', $studentID)->first()->fullname;
                            if(UserProfile::select('identification_number')->where('users_username', $studentID)->first() != null){
                                $studentIdentificationNumber = UserProfile::select('identification_number')->where('users_username', $studentID)->first()->identification_number;
                            }else{
                                $studentIdentificationNumber = "N/A";
                            }
                            $studentDetails = [
                                'name' => $studentName,
                                'identificationNumber' => $studentIdentificationNumber,
                                'matrixNumber' => $studentID
                            ];
                            $courseGrades = CourseGrade::join('courses', 'course_grades.courses_code', 'courses.code')->select('course_grades.credit_hour', 'course_grades.grade_pointer', 'courses.code', 'courses.name')->where('users_username', $studentID)->where('study_levels_code', $studyLevel)->where('semester', $semester)->get();
                            PDF::SetCreator('eKV');
                            PDF::SetAuthor('eKV');
                            PDF::SetTitle($title);
                            PDF::AddPage();
                            PDF::SetFont('helvetica', 'B', 10);
                            $settings = $this->instituteSettings;
                            if(isset($settings)){
                                if(empty($settings['institute_name'])){
                                    $instituteName = "Kolej Vokasional Malaysia";
                                }else{
                                    $instituteName = ucwords($settings['institute_name']);
                                }
                            }else{
                                $instituteName = "Kolej Vokasional Malaysia";
                            }
                            if(Storage::disk('local')->exists('public/img/system/logo-300.png')){
                                $logo = '.' . Storage::disk('local')->url('public/img/system/logo-300.png');
                            }elseif(Storage::disk('local')->exists('public/img/system/logo-def-300.png')){
                                $logo = '.' . Storage::disk('local')->url('public/img/system/logo-def-300.png');
                            }
                            // Header
                            PDF::Image($logo, 15, 10, 26, 26);
                            PDF::SetFont('helvetica', 'B', 12);
                            PDF::Multicell(0, 6, strtoupper($instituteName), 0, 'L', 0, 2, 42, 10);
                            PDF::SetFont('helvetica', '', 9);
                            PDF::Multicell(0, 10, 'ALAMAT INSTITUSI: ' . strtoupper($settings['institute_address']), 0, 'L', 0, 2, 42, 16);
                            PDF::Multicell(0, 5, 'ALAMAT E-MEL: ' . strtoupper($settings['institute_email_address']), 0, 'L', 0, 2, 42, 26);
                            PDF::Multicell(0, 5, 'NO. TELEFON PEJABAT: ' . strtoupper($settings['institute_phone_number']), 0, 'L', 0, 2, 42, 31);
                            PDF::Ln(1);
                            PDF::writeHTML("<hr>", true, false, false, false, '');
                            PDF::SetFont('helvetica', 'b', 10);
                            PDF::Multicell(0, 5, 'TRANSKRIP SEMESTER', 0, 'C', 0, 2, 10, 38);
                            PDF::Ln(1);
                            PDF::writeHTML("<hr>", true, false, false, false, '');
                            // Student Details
                            PDF::SetXY(10, 46);
                            PDF::SetFont('helvetica', '', 9);
                            PDF::MultiCell(95, 13, 'NAMA: ' . strtoupper($studentDetails['name']), 0, 'L', 0, 0, '', '', true);
                            PDF::MultiCell(95, 13, 'PERINGKAT PENGAJIAN: ' . strtoupper($studyLevelName), 0, 'L', 0, 0, '', '', true);
                            PDF::Ln();
                            PDF::MultiCell(95, 13, 'NO. K/P: ' . $studentDetails['identificationNumber'], 0, 'L', 0, 0, '', '', true);
                            PDF::MultiCell(95, 13, 'PROGRAM: ' . strtoupper($studentProgram), 0, 'L', 0, 0, '', '', true);
                            PDF::Ln();
                            PDF::MultiCell(95, 13, 'ANGKA GILIRAN: ' . strtoupper($studentDetails['matrixNumber']), 0, 'L', 0, 0, '', '', true);
                            PDF::MultiCell(95, 13, 'SEMESTER: ' . $semester, 0, 'L', 0, 0, '', '', true);
                            // Course Grade List
                            // Maximum: 15 courses
                            PDF::SetXY(10, 87);
                            PDF::writeHTML("<hr>", true, false, false, false, '');
                            PDF::SetXY(10, 88);
                            PDF::SetFont('helvetica', 'B', 9);
                            PDF::MultiCell(30, 5, 'KOD KURSUS', 0, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(100, 5, 'NAMA KURSUS', 0, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(30, 5, 'JAM KREDIT', 0, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(30, 5, 'NILAI GRED', 0, 'C', 0, 0, '', '', true);
                            PDF::SetFont('helvetica', '', 9);
                            foreach ($courseGrades as $courseGrade) { 
                                PDF::Ln();
                                PDF::MultiCell(30, 10, strtoupper($courseGrade->code), 0, 'C', 0, 0, '', '', true);
                                PDF::MultiCell(100, 10, strtoupper($courseGrade->name), 0, 'C', 0, 0, '', '', true);
                                PDF::MultiCell(30, 10, $courseGrade->credit_hour, 0, 'C', 0, 0, '', '', true);
                                PDF::MultiCell(30, 10, $courseGrade->grade_pointer, 0, 'C', 0, 0, '', '', true);
                            }
                            // Testing purposes
                            // for ($i=0; $i < 15; $i++){ 
                            //     PDF::Ln();
                            //     PDF::MultiCell(30, 10, 'Test', 0, 'C', 0, 0, '', '', true);
                            //     PDF::MultiCell(100, 10, 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Non corporis, voluptas enim quos est mollitia?', 0, 'C', 0, 0, '', '', true);
                            //     PDF::MultiCell(30, 10, 'Test', 0, 'C', 0, 0, '', '', true);
                            //     PDF::MultiCell(30, 10, 'Test', 0, 'C', 0, 0, '', '', true);
                            // }
                            // Semester Grade
                            PDF::SetXY(50, 245);
                            PDF::writeHTML("<hr>", true, false, false, false, '');
                            PDF::SetXY(10, 248);
                            PDF::SetFont('helvetica', 'B', 9);
                            PDF::MultiCell(90, 5, 'KOMPONEN', 1, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(50, 5, 'JUMLAH NILAI KREDIT', 1, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(50, 5, 'JUMLAH NILAI GRED', 1, 'C', 0, 0, '', '', true);
                            PDF::Ln();
                            PDF::MultiCell(90, 5, 'PNG SEMESTER SEMASA (PNGS)', 1, 'C', 0, 0, '', '', true);
                            PDF::SetFont('helvetica', '', 9);
                            PDF::MultiCell(50, 5, $semesterGrade->total_credit_gpa, 1, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(50, 5, $semesterGrade->gpa, 1, 'C', 0, 0, '', '', true);
                            PDF::Ln();
                            PDF::SetFont('helvetica', 'B', 9);
                            PDF::MultiCell(90, 5, 'PNG KUMULATIF KESELURUHAN (PNGKK)', 1, 'C', 0, 0, '', '', true);
                            PDF::SetFont('helvetica', '', 9);
                            PDF::MultiCell(50, 5, $semesterGrade->total_credit_cgpa, 1, 'C', 0, 0, '', '', true);
                            PDF::MultiCell(50, 5, $semesterGrade->cgpa, 1, 'C', 0, 0, '', '', true);
                            PDF::Ln();
                            PDF::SetXY(10, 265);
                            PDF::SetFont('helvetica', '', 8);
                            PDF::MultiCell(0, 5, 'Transkrip ini adalah janaan komputer.', 0, 'C', 0, 0, '', '', true);
                            PDF::Ln(3);
                            PDF::MultiCell(0, 5, 'Tandatangan tidak diperlukan.', 0, 'C', 0, 0, '', '', true);
                            PDF::Ln(3);
                            PDF::MultiCell(0, 5, 'Dijana menggunakan sistem eKV.', 0, 'C', 0, 0, '', '', true);
                            PDF::Output(strtoupper($studentName) . '_TRANSKRIP SEMESTER ' . $semester . '_' . strtoupper($studyLevelName) . '.pdf', 'D');
                        }else{
                            abort(404, 'Transkrip untuk pelajar ini tidak dijumpai!');
                        }
                    }else{
                        abort(404, 'Pelajar tidak diletakkan dalam kelas!');
                    }
                }else{
                    abort(404, 'Pengguna bukanlah seorang pelajar!');
                }
            }else{
                abort(404, 'Tiada pengguna dijumpai!');
            }
        }else{
            abort(403, 'Anda tiada akses pada laman ini!');
        }
    }
}