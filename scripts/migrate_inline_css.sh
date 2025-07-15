#!/usr/bin/env bash
set -e

# Files to use
dbstyles="inline_style_properties.txt"
utilfile="css/core/utilities.css"

# Append header to utilities file
echo >> "$utilfile"
echo "/* Auto-generated utility classes for inline styles */" >> "$utilfile"

# Generate utility classes for each unique inline style
Tail=$(tail -n +1 "$dbstyles")
while IFS= read -r style; do
  # Remove count prefix and trim
  decl="$(echo "$style" | sed -E 's/^[[:space:]]*[0-9]+[[:space:]]*//')"
  # Skip dynamic or PHP tag declarations
  if echo "$decl" | grep -q '[\$<>?]'; then
    continue
  fi
  # Create safe class name
  classname="u-$(echo "$decl" | sed -E 's/: (.*)/-\1/' | sed -E 's/;.*//' | sed -E 's/[^a-zA-Z0-9]+/-/g' | sed -E 's/^-|-$//g' | sed -E 's/-+/--/g')"
  # Append class to utilities
  echo ".$classname { $decl; }" >> "$utilfile"
done < <(tail -n +1 "$dbstyles")

# Replace inline style attributes with utility classes in all PHP files
find . -type f -name "*.php" | while IFS= read -r file; do
  content=$(cat "$file")
  # For each style declaration, replace style="..." with class
  while IFS= read -r style; do
    decl="$(echo "$style" | sed -E 's/^[[:space:]]*[0-9]+[[:space:]]*//')"
    # Skip dynamic or PHP tag declarations
    if echo "$decl" | grep -q '[\$<>?]'; then
      continue
    fi
    classname="u-$(echo "$decl" | sed -E 's/: (.*)/-\1/' | sed -E 's/;.*//' | sed -E 's/[^a-zA-Z0-9]+/-/g' | sed -E 's/^-|-$//g' | sed -E 's/-+/--/g')"
    # Replace inline style with utility class using literal match
    perl -i -pe "s/\Qstyle=\"$decl\"\E/class=\"$classname\"/g" "$file"
  done < <(tail -n +1 "$dbstyles")
done

# Remove any leftover empty style attributes
find . -type f -name "*.php" -exec sed -i '' -E 's/\s*style=\"\"//g' {} \

echo "Migration complete: inline styles moved to utility classes." 