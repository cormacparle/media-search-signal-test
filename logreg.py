import pandas as pd
import numpy as np
from sklearn import preprocessing
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn import metrics
from sklearn.feature_selection import RFE
from statsmodels.stats.outliers_influence import variance_inflation_factor
import statsmodels.api as smapi

# load the data from the csv file
data = pd.read_csv( 'out/MediaSearch_20210127.csv', header=0 )

logreg = LogisticRegression(fit_intercept=True, solver='liblinear')

# NAMING CONVENTIONS
#
# X is an array containing the dependent variables - i.e. the elasticsearch scores for each search component
# y is an array containing the independent variable - i.e. the rating for the image

dependent_variable_columns = [
  'descriptions.plain',
  'descriptions',
  'title',
  'title.plain',
  'category',
  'redirect.title',
  'redirect.title.plain',
  'suggest',
  'auxiliary_text',
  'auxiliary_text.plain',
  'text',
  'text.plain',
  'statements'
]
X = data.loc[:, dependent_variable_columns]
y = data.loc[:, data.columns == 'rating']

# First we need to figure out which search signals (i.e. dependent variables) we should use
# If we use them all we get negative weights, which we can't use in elasticsearch, so let's eliminate
# some of them in a sensible manner
#
# Step 1
# See which search signals are statistically significant by fitting a logistic regression model

logit_model=smapi.Logit(y,X)
result=logit_model.fit()
print('CHECKING FOR STATISICAL SIGNIFICANCE')
print('===')
print(result.summary2())

# Here are the results
#                            Results: Logit
# ====================================================================
# Model:                Logit            Pseudo R-squared: 0.081
# Dependent Variable:   rating           AIC:              9514.0487
# Date:                 2021-03-25 14:13 BIC:              9603.9720
# No. Observations:     7459             Log-Likelihood:   -4744.0
# Df Model:             12               LL-Null:          -5161.7
# Df Residuals:         7446             LLR p-value:      4.1831e-171
# Converged:            1.0000           Scale:            1.0000
# No. Iterations:       5.0000
# --------------------------------------------------------------------
#                       Coef.  Std.Err.    z    P>|z|   [0.025  0.975]
# --------------------------------------------------------------------
# descriptions.plain   -0.0266   0.0290 -0.9196 0.3578 -0.0834  0.0301
# descriptions          0.0545   0.0287  1.8961 0.0579 -0.0018  0.1108
# title                 0.0601   0.0110  5.4793 0.0000  0.0386  0.0816
# title.plain           0.0154   0.0115  1.3393 0.1805 -0.0071  0.0379
# category              0.0348   0.0034 10.2257 0.0000  0.0282  0.0415
# redirect.title       -0.0165   0.0190 -0.8685 0.3851 -0.0537  0.0207
# redirect.title.plain  0.0060   0.0193  0.3093 0.7571 -0.0319  0.0438
# suggest              -0.0069   0.0049 -1.4115 0.1581 -0.0166  0.0027
# auxiliary_text       -0.0666   0.0095 -7.0313 0.0000 -0.0852 -0.0481
# auxiliary_text.plain  0.0248   0.0087  2.8370 0.0046  0.0077  0.0419
# text                 -0.0701   0.0163 -4.2982 0.0000 -0.1021 -0.0382
# text.plain            0.0415   0.0168  2.4720 0.0134  0.0086  0.0743
# statements            0.0687   0.0063 10.9180 0.0000  0.0564  0.0810
# ====================================================================
#
# Anything with P>|z| < 0.05 is statistically significant, so that suggests we should ignore
# descriptions.plain, title.plain, redirect.title, redirect.title.plain, suggest and possibly
# descriptions

# Step 2
# Check for multicollinearity
# If 2 dependent variables vary in similar ways, then they're likely to not be independent of one another,
# and so we probably shouldn't include both of them. We test for this using variance influence factor AKA VIF

print('CHECKING FOR MULTICOLLINEARITY')
print('===')
vif_data = pd.DataFrame()
vif_data["feature"] = X.columns
vif_data["VIF"] = [variance_inflation_factor(X.values, i)
                          for i in range(len(X.columns))]
print(vif_data)

# Here are the results
#                 feature        VIF
#0     descriptions.plain  32.414879
#1           descriptions  31.941416
#2                  title  30.542579
#3            title.plain  32.308208
#4               category   2.538902
#5         redirect.title  18.091665
#6   redirect.title.plain  18.191382
#7                suggest   9.869167
#8         auxiliary_text  37.705318
#9   auxiliary_text.plain  29.930303
#10                  text  24.548127
#11            text.plain  25.251712
#12            statements   1.122044
#
# Anything with VIF>10 is highly collinear with something else. The obvious thing here is <field> and <field>.plain -
# it's kind of obvious that they're pretty collinear, so let's remove those and re-run

print('CHECKING FOR MULTICOLLINEARITY - SECOND PASS')
print('===')
dependent_variable_columns = [
  #'descriptions.plain',
  'descriptions',
  'title',
  #'title.plain',
  'category',
  'redirect.title',
  #'redirect.title.plain',
  'suggest',
  'auxiliary_text',
  #'auxiliary_text.plain',
  'text',
  #'text.plain',
  'statements'
]
X = data.loc[:, dependent_variable_columns]
vif_data = pd.DataFrame()
vif_data["feature"] = X.columns
vif_data["VIF"] = [variance_inflation_factor(X.values, i)
                          for i in range(len(X.columns))]
print(vif_data)

# Here are the results
#          feature       VIF
#0    descriptions  2.200676
#1           title  9.351241
#2        category  2.524780
#3  redirect.title  1.216832
#4         suggest  6.787788
#5  auxiliary_text  6.199441
#6            text  2.371407
#7      statements  1.119669
#
# Much better ... but really our target for VIF is <2.5

# Step 3
# Reduce dependent variables further
#
# Because we still have some multicollinearity, let's see if we can get rid of some of the fields to reduce it
#
# We can use Recursive Feature Elimination (RFE) to "select those features (columns) in a training dataset that are
# more or most relevant in predicting the target variable"
#
# ATM we have 8 dependent variables, so let's iterate, reducing the number by 1 each time, and checking
# whether we have any negative coefficients, plus some measures of precision/accuracy

for i in range(len(dependent_variable_columns), 1, -1):
    # find the most significant fields
    significantColumns  = []
    rfe = RFE(logreg, n_features_to_select=i)
    rfe = rfe.fit(X, y.values.ravel())
    support = dict(zip(list(X.columns), rfe.support_.ravel()))
    for key, value in support.items():
        if value:
            significantColumns.append( key )

    X = data.loc[:, significantColumns]

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=0)

    model = logreg.fit(X_train, y_train.values.ravel())
    print('Coefficients')
    print(dict(zip(list(X.columns), model.coef_[0])))
    print('Intercept')
    print(model.intercept_[0])

    # See how accurate the model is
    y_pred = logreg.predict(X_test)
    y_pred_p = logreg.predict_proba(X_test)
    print('Accuracy of logistic regression classifier on test set: {:.2f}'.format(logreg.score(X_test, y_test)))
    print('Balanced accuracy: {:.4f}'.format(metrics.balanced_accuracy_score(y_test, y_pred)))
    print('Average precision score: {:.4f}'.format(metrics.average_precision_score(y_test, y_pred_p.T[1])))
    print('Brier score loss (smaller is better): {:.4f}'.format(metrics.brier_score_loss(y_test, y_pred_p.T[1])))
    print('F1 score: {:.4f}'.format(metrics.average_precision_score(y_test, y_pred)))

# The first set of coefficients with all positive values that we have is
# {'descriptions': 0.019320230186222098, 'title': 0.0702949038300864, 'category': 0.05158078808882278,
# 'redirect.title': 0.01060150471482338, 'statements': 0.11098311564161133}
# Intercept: -1.1975600089068401
#
# By a lucky coincidence, this also gives us the best set of accuracy measures, and also gives us all VIFs<2.5
# So let's use these!