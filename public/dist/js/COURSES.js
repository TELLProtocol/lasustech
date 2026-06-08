const data = {
  "year_one": {
    "first_semester": [
      { "code": "MTH101", "title": "Elementary Mathematics I" },
      { "code": "COS101", "title": "Introduction to Computing Sciences" },
      { "code": "PHY101", "title": "General Physics I" },
      { "code": "PHY107", "title": "General Physics Practical I" },
      { "code": "GST111", "title": "Communication in English" },
      { "code": "STA111", "title": "Descriptive Statistics" },
      { "code": "LASUSTECH-LIB101", "title": "Use of Library, Study Skills and ICT" },
      { "code": "LASUSTECH-CSC103", "title": "Internet Technology" },
      { "code": "LASUSTECH-YOR101", "title": "Communication in Yoruba 1" },
      { "code": "CHM101", "title": "General Chemistry I" },
      { "code": "CHM107", "title": "General Chemistry Practical I" },
      { "code": "BIO101", "title": "General Biology I" },
      { "code": "BIO107", "title": "General Biology Practical I" }
    ],
    "second_semester": [
      { "code": "MTH102", "title": "Elementary Mathematics II (Calculus)" },
      { "code": "COS102", "title": "Problem Solving" },
      { "code": "PHY102", "title": "General Physics II" },
      { "code": "PHY108", "title": "General Physics Practical II" },
      { "code": "GST112", "title": "Nigerian Peoples and Culture" },
      { "code": "LASUSTECH-CSC106", "title": "Website Design and Management" },
      { "code": "LASUSTECH-FRE102", "title": "French Language for Science Student" },
      { "code": "LASUSTECH-YOR102", "title": "Communication in Yoruba 2" },
      { "code": "CHM102", "title": "General Chemistry II" },
      { "code": "CHM108", "title": "General Chemistry Practical II" },
      { "code": "FRS102", "title": "Introductory Forensic Science" },
      { "code": "STA112", "title": "Probability I" }
    ]
  },

  "year_two": {
    "first_semester": [
      { "code": "COS201", "title": "Computer Programming I" },
      { "code": "CSC203", "title": "Discrete Structures" },
      { "code": "MTH201", "title": "Mathematical Method I" },
      { "code": "ENT211", "title": "Entrepreneurship and Innovation" },
      { "code": "IFT211", "title": "Digital Logic Design" },
      { "code": "SEN201", "title": "Introduction to Software Engineering" },
      { "code": "CYB201", "title": "Introduction to Cybersecurity and Strategy" },
      { "code": "ICT201", "title": "Introduction to Information and Communication Technology" },
      { "code": "LASUSTECH-AGR215", "title": "General Agricultural Practice" }
    ],
    "second_semester": [
      { "code": "GST212", "title": "Philosophy, Logic & Human Existence" },
      { "code": "COS202", "title": "Computer Programming II" },
      { "code": "MTH202", "title": "Elementary Differential Equations" },
      { "code": "IFT212", "title": "Computer Architecture and Organisation" },
      { "code": "INS204", "title": "Systems Analysis and Design" },
      { "code": "PHY202", "title": "Electric Circuits and Electronics" },
      { "code": "CSC299", "title": "SIWES I" },
      { "code": "LASUSTECH-GET230", "title": "General Workshop Practice" },
      { "code": "LASUSTECH-CSC206", "title": "Web Server Administration" }
    ]
  },

  "year_three": {
    "first_semester": [
      { "code": "CSC301", "title": "Data Structures" },
      { "code": "CSC309", "title": "Artificial Intelligence" },
      { "code": "CSC399", "title": "SIWES II" },
      { "code": "ICT305", "title": "Data Communication System and Networks" },
      { "code": "LASUSTECH-CSC307", "title": "Cloud Computing" },
      { "code": "LASUSTECH-CSC311", "title": "Information Storage Management" },
      { "code": "SEN301", "title": "Object-Oriented Analysis and Design" },
      { "code": "ICT301", "title": "Satellite Communication" },
      { "code": "CYB301", "title": "Cryptography Techniques, Algorithms and Algorithms" }
    ],
    "second_semester": [
      { "code": "GST312", "title": "Peace and Conflict Resolution" },
      { "code": "ENT312", "title": "Venture Creation" },
      { "code": "CSC308", "title": "Operating Systems" },
      { "code": "CSC322", "title": "Computer Science Innovation and New Technologies" },
      { "code": "DTS304", "title": "Data Management I" },
      { "code": "LASUSTECH-CSC304", "title": "Computer System Security" },
      { "code": "LASUSTECH-CSC302", "title": "Survey of Programming Languages" },
      { "code": "LASUSTECH-CSC310", "title": "Machine Learning" }
    ]
  },

  "year_four": {
    "first_semester": [
      { "code": "CSC401", "title": "Algorithms and Complexity Analysis" },
      { "code": "COS409", "title": "Research Methodology and Technical Report Writing" },
      { "code": "INS401", "title": "Project Management" },
      { "code": "CSC497", "title": "Final Year Project I" },
      { "code": "LASUSTECH-CSC405", "title": "Operating System Engineering" },
      { "code": "LASUSTECH-CSC403", "title": "Blockchain and its Application" },
      { "code": "LASUSTECH-CSC435", "title": "Computer Graphics and Visualization" },
      { "code": "LASUSTECH-CSC437", "title": "Data Mining and Big Data Analytics" }
    ],
    "second_semester": [
      { "code": "CSC402", "title": "Ethics and Legal Issues in Computer Science" },
      { "code": "CSC498", "title": "Final Year Project II" },
      { "code": "ICT418", "title": "Design & Installation of Electrical & ICT Equipment" },
      { "code": "IFT442", "title": "Wireless Communications and Networking" },
      { "code": "LASUSTECH-CSC442", "title": "Mobile Application Development" },
      { "code": "LASUSTECH-CSC422", "title": "Human-Computer Interaction" },
      { "code": "LASUSTECH-CSC454", "title": "Semantic Web Computing" },
      { "code": "LASUSTECH-CSC406", "title": "Fault Tolerance Computing" },
      { "code": "LASUSTECH-CSC408", "title": "Game Design" }
    ]
  }
};

const yearSemesterSelect = document.querySelector('#yearSemester');
const courseSelect = document.querySelector('#courses');

yearSemesterSelect.addEventListener('change', function () {
	const value = this.value;
	
	// reset courses
	courseSelect.innerHTML = '<option value="">Select Course</option>';
	
	if (!value) return;
	
	const [year, semester] = value.split(':');
	const courses = data?.[year]?.[semester];
	
	if (!courses) return;
	
	courses.forEach(course => {
		const option = document.createElement('option');
		option.value = course.code;
		option.textContent = `${course.code} - ${course.title}`;
		courseSelect.appendChild(option);
	});
});