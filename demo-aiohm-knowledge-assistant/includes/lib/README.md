# Third-Party Libraries

This directory contains third-party libraries used by the AIOHM Knowledge Assistant plugin:

## Smalot/PdfParser
- **Purpose**: PDF content extraction and parsing
- **Version**: Included version
- **License**: LGPL-3.0
- **Source**: https://github.com/smalot/pdfparser

## FPDF
- **Purpose**: PDF generation for conversation exports
- **Version**: Included version  
- **License**: Free to use
- **Source**: http://www.fpdf.org

## WordPress Plugin Check Notes

These libraries may generate WordPress Plugin Check warnings related to output escaping. These warnings are expected and acceptable for the following reasons:

### Exception Messages (Parser.php, Encoding.php, RawDataParser.php)
- **Lines**: Parser.php:328, Encoding.php:157, RawDataParser.php:140,410
- **Issue**: Exception messages containing variables
- **Why acceptable**: These are internal exception messages used for debugging and error handling within the library, not user-facing output

### PDF Generation Output (fpdf.php)
- **Lines**: fpdf.php:267, 1013, 1022
- **Issue**: Direct output for PDF generation
- **Why acceptable**: FPDF uses direct output to generate PDF content, which is the intended behavior for PDF generation libraries

### Security Assessment
1. **No user input**: These libraries process structured data formats (PDF) and don't handle direct user input
2. **Internal processing**: All flagged output is for internal library operations
3. **Established libraries**: Both libraries are widely used and maintained
4. **Plugin integration**: The main plugin code properly sanitizes all data before passing to these libraries

The main plugin code properly sanitizes and escapes all user-facing output that interacts with these libraries.