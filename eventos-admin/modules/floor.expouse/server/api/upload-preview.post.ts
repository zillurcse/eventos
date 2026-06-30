import { defineEventHandler, readMultipartFormData } from 'h3';
import { writeFile } from 'fs/promises';
import { join } from 'path';
import { randomUUID } from 'crypto';

export default defineEventHandler(async (event) => {
  const formData = await readMultipartFormData(event);
  
  if (!formData) {
    throw createError({
      statusCode: 400,
      statusMessage: 'No file uploaded'
    });
  }

  const file = formData.find(f => f.name === 'image');
  if (!file || !file.data) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Missing image data'
    });
  }

  // Generate unique filename
  const filename = `${randomUUID()}.png`;
  
  // Assuming 'public' is at the root of the project
  const uploadDir = join(process.cwd(), 'public', 'designs');
  const filePath = join(uploadDir, filename);

  try {
    await writeFile(filePath, file.data);
    return {
      success: true,
      imageUrl: `/designs/${filename}`
    };
  } catch (error) {
    console.error('Upload Error:', error);
    throw createError({
      statusCode: 500,
      statusMessage: 'Failed to save image'
    });
  }
});
