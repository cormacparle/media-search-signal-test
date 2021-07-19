import getopt
import numpy as np
import os
import pandas as pd
from sklearn import preprocessing
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn import metrics
from sklearn.feature_selection import RFE
from statsmodels.stats.outliers_influence import variance_inflation_factor
import statsmodels.api as smapi
import subprocess
import sys

ranklibFile = 'out/MediaSearch_20210127.tsv'

trainingDataSize = "0.8"
generateNewCsv = True
try:
    opts, args = getopt.getopt(sys.argv[1:],"hx", [ "trainingDataSize=" ])
except getopt.GetoptError:
    print('ERROR: Incorrect options')
    sys.exit(2)
for opt, arg in opts:
    if opt == '-h':
        print('')
        print('Run logistic regression on ' + ranklibFile + ' and output scores and coefficients')
        print('')
        print('The ranklib file is first transformed to a csv file for processing using ranklibToCsv.php')
        print('')
        print('logreg.py -x --trainingDataSize={number <= 1}')
        print('')
        print('The option -x skips the transformation to csv, and uses the csv from the last run')
        print('If trainingDataSize is set, the data will be trained on the first (total_rows)*trainingDataSize rows of the csv')
        print('')
        sys.exit()
    elif opt == "--trainingDataSize":
        trainingDataSize = float(arg)
    if opt == '-x':
        generateNewCsv = False

if ( generateNewCsv == True ):
    # transform the ranklib file to a csv file for processing
    subprocess.check_output( ["php", "ranklibToCsv.php", "--ranklibFile=" + ranklibFile ] )

# load the data from the csv file
alldata = pd.read_csv( ranklibFile.replace( '.tsv', '.csv' ), header=0 )
trainingData = alldata[:round(len(alldata)*trainingDataSize)]
testData = alldata[round(len(alldata)*trainingDataSize) + 1:]
print('Training on the first ' + str(round(len(alldata)*trainingDataSize)) + ' rows of ' + ranklibFile.replace( '.tsv', '.csv' ))
logreg = LogisticRegression(fit_intercept=True, solver='liblinear')

# NAMING CONVENTIONS
#
# X(_(train|test)) -> array containing the dependent variables - i.e. the elasticsearch scores for each search component
# y(_(train|test)) is an array containing the independent variable - i.e. the rating for the image

y = alldata.loc[:, alldata.columns == 'rating']
y_train = trainingData.loc[:, trainingData.columns == 'rating']
y_test = testData.loc[:, testData.columns == 'rating']
# exclude obviously highly-collinear variables, and use "plain" because not all languages have stemmed fields
dependent_variable_columns = [
  'descriptions.plain',
  #'descriptions',
  #'title',
  'title.plain',
  'category',
  #'redirect.title',
  'redirect.title.plain',
  'suggest',
  #'auxiliary_text',
  'auxiliary_text.plain',
  #'text',
  'text.plain',
  'statements'
]
X = alldata.loc[:, dependent_variable_columns]
X_train = trainingData.loc[:, dependent_variable_columns]
X_test = testData.loc[:, dependent_variable_columns]

# We need to have all positive coefficients for elasticsearch
#
# We can use Recursive Feature Elimination (RFE) to "select those features (columns) in a training dataset that are
# more or most relevant in predicting the target variable"
#
# Use RFE to reduce the number of dependent variables until we get all positive coefficients
#
# Optimise for AVERAGE PRECISION over the entire dataset

bestPrecision = 0
coeffsWithBestPrecision = {}
interceptWithBestPrecision = 0
for i in range(len(dependent_variable_columns), 1, -1):
    # find the most significant fields
    significantColumns  = []
    rfe = RFE(logreg, n_features_to_select=i)
    rfe = rfe.fit(X, y.values.ravel())
    support = dict(zip(list(X.columns), rfe.support_.ravel()))
    for key, value in support.items():
        if value:
            significantColumns.append( key )

    X_train = trainingData.loc[:, significantColumns]
    X_test = testData.loc[:, significantColumns]

    # X_train, X_test, y_train, y_test = train_test_split(X, y, train_size=0.8, random_state=0)

    model = logreg.fit(X_train, y_train.values.ravel())
    coeffs = dict(zip(list(X.columns), model.coef_[0]))

    # optimise for average precision
    y_pred = logreg.predict(X_test)
    y_pred_p = logreg.predict_proba(X_test)
    precision = metrics.average_precision_score(y_test, y_pred_p.T[1], average="micro")
    if precision > bestPrecision:
        if (len([x for x in model.coef_[0] if float(x) < 0])) == 0:
            bestPrecision = precision
            coeffsWithBestPrecision = coeffs
            interceptWithBestPrecision = model.intercept_[0]

print('Best average precision score: {:.4f}'.format(bestPrecision))
print('Coefficients')
print(coeffsWithBestPrecision)
print('Intercept')
print(interceptWithBestPrecision)

