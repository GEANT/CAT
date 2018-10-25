FILE := main.py

help:
	@echo "check test"

check:
	flake8 $(FILE) --max-line-length=80
	pep8 $(FILE)
	isort $(FILE) --recursive --check-only --diff
	pylint3 $(FILE) 

 test:
	pip install -r requirements_dev.txt
	# run tests

