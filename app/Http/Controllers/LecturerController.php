<?php

namespace App\Http\Controllers;
use App\Models\Lecturer;
use App\Models\Faculty;
use App\Models\Course;
use App\Models\Teach;
use App\Models\Submit;
use App\Models\Assignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LecturerController extends Controller
{
    
    public function dashboard()
    {
        $activeNavItem = 'dashboard';

        $user = Auth::user(); 
        $lecturer = $user->lecturer; 
    
        if ($lecturer) {
            $faculty_id = $lecturer->faculty_id;
            $faculty = Faculty::find($faculty_id);
            $faculties = Faculty::all();
    return view('lecturer/dashboard', compact('activeNavItem', 'lecturer', 'faculty','faculties'));
    }
    }

    public function updateProfile(Request $request)
{
    // Validate the request data
    $request->validate([
        'name' => 'required|string|max:255',
        'faculty_id' => 'required|exists:faculties,id', 
    ]);

    // Get the authenticated user and their associated lecturer
    $user = Auth::user(); 
    $lecturer = $user->lecturer;

    // Update lecturer's name
    $lecturer->name = $request->input('name');
    
    // Update lecturer's faculty ID
    $lecturer->faculty_id = $request->input('faculty_id');
    
    // Save the changes
    $lecturer->save();
    
    // Redirect back to the dashboard with a success message
    return redirect('/dashboard')->with('success', 'Profile updated successfully.');
}


    public function registerCourse()
    {
        $activeNavItem = 'registerCourse';

    // Get the authenticated user and their associated lecturer
    $user = Auth::user(); 
    $lecturer = $user->lecturer;

    $faculty_id = $lecturer->faculty_id;
    $faculty = Faculty::find($faculty_id);

    $courses = Course::all();

        return view('lecturer/registerCourse', compact('activeNavItem','courses', 'lecturer', 'faculty'));
    }

    public function addCourse(Request $request)
{
    // Validate the incoming request data
    $request->validate([
        'course_id' => 'required|exists:courses,id', // Check if the course ID exists in the courses table
        'faculty_id' => 'required|exists:faculties,id', // Check if the faculty ID exists in the faculties table
    ]);

    // Get the authenticated user and their associated lecturer
    $user = Auth::user(); 
    $lecturer = $user->lecturer;
    $lecturer_id = $lecturer->id;

    // Check if the lecturer has already registered for the same course
    if (Teach::where('lecturer_id', $lecturer_id)->where('course_id', $request->input('course_id'))->exists()) {
        // If the course is already registered, return with an error message
        return redirect()->back()->with('error', 'You have already registered for this course.');
    }

    // Create a new Teach record
    $teach = new Teach();
    $teach->lecturer_id = $lecturer_id;
    $teach->faculty_id = $request->input('faculty_id');
    $teach->course_id = $request->input('course_id');

    // Save the new record
    $teach->save();
    
    // Redirect with a success message
    return redirect('/take-course/assign')->with('success', 'Course registered successfully.');
}

    public function manageCourse()
    {
        $activeNavItem = 'manageCourse';

      // Get the lecturer ID 
      $user = Auth::user(); 
      $lecturer = $user->lecturer;

    // Retrieve all the teaches associated with the lecturer
    $teaches = $lecturer->teaches;

    return view('lecturer/manageCourse', compact('activeNavItem', 'teaches'));
    }

    public function deleteTeach(Teach $teach)
{
    $teach->delete();

    return redirect()->back()->with('success', 'Teach record deleted successfully');
}

    public function assignAssignment()
    {
        $activeNavItem = 'registerAssignment';
        
        // Get the lecturer ID 
        $user = Auth::user(); 
        $lecturer = $user->lecturer;
        $teaches = $lecturer->teaches;

        return view('lecturer/registerAssignment', compact('activeNavItem','teaches'));
    }

    
    public function addAssignment(Request $request)
{
    // Validate the incoming request data
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'teach_id' => 'required|exists:teaches,id', // assuming the course id is the reference here
        'status' => 'required|in:Active,Inactive',
        'dateline' => 'required|date_format:Y-m-d\TH:i', // Validate date-time format
        'fileInput' => 'required|file|max:10240', // Max 10MB file size
    ]);

    // Check if validation fails
    if ($validator->fails()) {
        return redirect()->back()->withErrors($validator)->withInput();
    }

    // Find the course entry to ensure it exists
    $teach_id = $request->input('teach_id');
    
    // Create a new assignment instance
    $assignment = new Assignment();
    $assignment->title = $request->input('title');
    $assignment->teach_id = $teach_id; // using teach_id as course_id based on the form
    $assignment->status = $request->input('status');
    $assignment->dateline = $request->input('dateline');
    $assignment->created_at = now(); // Manually set created_at timestamp

    // Upload and store the assignment file
    if ($request->hasFile('fileInput')) {
        $file = $request->file('fileInput');
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('assignment_files', $fileName, 'public'); // Store file in public storage
        $assignment->assignmentDetails_file = $filePath;
    }

    // Save the assignment
    $assignment->save();

    // Redirect back with success message
    return redirect()->back()->with('success', 'Assignment added successfully.');
}

public function manageAssignment()
{
    $activeNavItem = 'manageAssignment';
    
    // Get the lecturer ID 
    $user = Auth::user(); 
    $lecturer = $user->lecturer;

    // Retrieve all the teaches associated with the lecturer
    $teach_ids = $lecturer->teaches()->pluck('id');

    // Retrieve all assignments that have teach_ids associated with the lecturer
    $assignments = Assignment::whereIn('teach_id', $teach_ids)->get();

    return view('lecturer/manageAssignment', compact('activeNavItem', 'assignments'));
}


    public function toggleAssignmentStatus($id)
    {
        // Find the assignment by ID
        $assignment = Assignment::find($id);

        if ($assignment) {
            // Toggle the status
            $assignment->status = ($assignment->status == 'Active') ? 'Inactive' : 'Active';
            $assignment->save();

            return redirect()->back()->with('success', 'Assignment status updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Assignment not found.');
        }
    }

    public function deleteAssignment($id)
{
    // Find the assignment by ID
    $assignment = Assignment::find($id);

    if ($assignment) {
        // Delete the assignment
        $assignment->delete();
        return redirect()->back()->with('success', 'Assignment deleted successfully.');
    } else {
        return redirect()->back()->with('error', 'Assignment not found.');
    }
}

    public function manageDetailAssignment($id)
    {
        $activeNavItem = 'manageAssignment';

        $assignment = Assignment::find($id);

        if (!$assignment) {
            return redirect()->back()->with('error', 'Assignment not found.');
        }

        $submits = Submit::where('assignment_id', $id)->get();
        $lecturer = $assignment->lecturer; // Assuming you have a relationship set up
        $course = $assignment->course; // Assuming you have a relationship set up

        return view('lecturer/manageDetailAssignment', compact('activeNavItem', 'assignment', 'submits', 'lecturer', 'course'));
    }

    public function giveMarks(Request $request, $id)
{

    $submit = Submit::find($id);

    if (!$submit) {
        return redirect()->back()->with('error', 'Submission not found.');
    }

    $request->validate([
        'marks' => 'required|numeric|min:0|max:100',
    ]);

    $submit->marks = $request->input('marks');
    $submit->save();

    return redirect()->back()->with('success', 'Marks updated successfully.');
}
 
}